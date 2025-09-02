@extends('layouts.app')

@section('title', isset($document) ? 'Chỉnh sửa Tài liệu' : 'Upload Tài liệu Mới')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">{{ isset($document) ? 'Chỉnh sửa Tài liệu' : 'Upload Tài liệu Mới' }}</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.location.href='/documents'">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <form id="document-form" class="needs-validation" novalidate enctype="multipart/form-data">
        @if(isset($document))
            <input type="hidden" id="document-id" value="{{ $document->id }}">
        @endif
        
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Thông tin tài liệu</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="title" class="required">Tiêu đề tài liệu</label>
                                    <input type="text" class="form-control" id="title" name="title" required
                                           value="{{ isset($document) ? $document->title : '' }}">
                                    <div class="invalid-feedback">Vui lòng nhập tiêu đề tài liệu</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project_id" class="required">Dự án</label>
                                    <select class="form-control" id="project_id" name="project_id" required>
                                        <option value="">Chọn dự án</option>
                                        <!-- Options will be loaded via AJAX -->
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn dự án</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="linked_entity_type">Liên kết với</label>
                                    <select class="form-control" id="linked_entity_type" name="linked_entity_type">
                                        <option value="">Không liên kết</option>
                                        <option value="task" {{ (isset($document) && $document->linked_entity_type === 'task') ? 'selected' : '' }}>Công việc</option>
                                        <option value="diary" {{ (isset($document) && $document->linked_entity_type === 'diary') ? 'selected' : '' }}>Nhật ký</option>
                                        <option value="cr" {{ (isset($document) && $document->linked_entity_type === 'cr') ? 'selected' : '' }}>Yêu cầu thay đổi</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-12" id="linked-entity-container" style="display: none;">
                                <div class="form-group">
                                    <label for="linked_entity_id">Chọn đối tượng liên kết</label>
                                    <select class="form-control" id="linked_entity_id" name="linked_entity_id">
                                        <option value="">Chọn đối tượng</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description">Mô tả</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"
                                              placeholder="Mô tả chi tiết về tài liệu...">{{ isset($document) ? $document->description : '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- File Upload Section -->
                @if(!isset($document))
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Upload tài liệu</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="file" class="required">Chọn file</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file" name="file" required
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.rar">
                                <label class="custom-file-label" for="file">Chọn file...</label>
                            </div>
                            <small class="form-text text-muted">
                                Định dạng hỗ trợ: PDF, Word, Excel, PowerPoint, Hình ảnh, ZIP, RAR (Tối đa 50MB)
                            </small>
                            <div class="invalid-feedback">Vui lòng chọn file để upload</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="version_comment">Ghi chú phiên bản</label>
                            <textarea class="form-control" id="version_comment" name="version_comment" rows="2"
                                      placeholder="Ghi chú về phiên bản này..."></textarea>
                        </div>
                    </div>
                </div>
                @else
                <!-- New Version Upload -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Upload phiên bản mới</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="new_file">Chọn file mới</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="new_file" name="new_file"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.rar">
                                <label class="custom-file-label" for="new_file">Chọn file...</label>
                            </div>
                            <small class="form-text text-muted">
                                Để trống nếu chỉ cập nhật thông tin metadata
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_version_comment">Ghi chú phiên bản mới</label>
                            <textarea class="form-control" id="new_version_comment" name="new_version_comment" rows="2"
                                      placeholder="Ghi chú về phiên bản mới..."></textarea>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Document Info -->
                @if(isset($document))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Thông tin tài liệu</h5>
                    </div>
                    <div class="card-body">
                        <div class="stat-item">
                            <label>Phiên bản hiện tại:</label>
                            <span class="stat-value">v{{ $document->current_version->version_number ?? 1 }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Kích thước:</label>
                            <span class="stat-value">{{ $document->current_version ? formatFileSize($document->current_version->file_size) : 'N/A' }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Định dạng:</label>
                            <span class="stat-value">{{ $document->current_version ? strtoupper(pathinfo($document->current_version->file_path, PATHINFO_EXTENSION)) : 'N/A' }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Tạo bởi:</label>
                            <span class="stat-value">{{ $document->creator->name ?? 'N/A' }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Ngày tạo:</label>
                            <span class="stat-value">{{ $document->created_at ? $document->created_at->format('d/m/Y H:i') : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Version History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Lịch sử phiên bản</h5>
                    </div>
                    <div class="card-body">
                        <div id="version-history">
                            <!-- Will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Thao tác nhanh</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-upload"></i> {{ isset($document) ? 'Cập nhật' : 'Upload tài liệu' }}
                            </button>
                            
                            @if(isset($document))
                            <button type="button" class="btn btn-outline-info" onclick="downloadDocument({{ $document->id }})">
                                <i class="icon-download"></i> Tải xuống
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="viewDocument({{ $document->id }})">
                                <i class="icon-eye"></i> Xem chi tiết
                            </button>
                            
                            <button type="button" class="btn btn-outline-danger" onclick="deleteDocument({{ $document->id }})">
                                <i class="icon-trash"></i> Xóa tài liệu
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Upload Progress Modal -->
<div class="modal fade" id="upload-progress-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đang upload tài liệu</h5>
            </div>
            <div class="modal-body">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         id="upload-progress-bar" style="width: 0%"></div>
                </div>
                <p class="mt-2 mb-0" id="upload-status">Đang chuẩn bị upload...</p>
            </div>
        </div>
    </div>
</div>

<script>
class DocumentFormManager {
    constructor() {
        this.isEdit = document.getElementById('document-id') !== null;
        this.initializeForm();
        this.loadProjects();
        if (this.isEdit) {
            this.loadVersionHistory();
        }
    }

    initializeForm() {
        const form = document.getElementById('document-form');
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (form.checkValidity()) {
                this.saveDocument();
            } else {
                form.classList.add('was-validated');
            }
        });
        
        // File input change handler
        const fileInput = document.getElementById('file');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                const fileName = e.target.files[0]?.name || 'Chọn file...';
                e.target.nextElementSibling.textContent = fileName;
                
                // Auto-fill title if empty
                const titleInput = document.getElementById('title');
                if (!titleInput.value && e.target.files[0]) {
                    const nameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
                    titleInput.value = nameWithoutExt;
                }
            });
        }
        
        const newFileInput = document.getElementById('new_file');
        if (newFileInput) {
            newFileInput.addEventListener('change', (e) => {
                const fileName = e.target.files[0]?.name || 'Chọn file...';
                e.target.nextElementSibling.textContent = fileName;
            });
        }
        
        // Linked entity type change handler
        document.getElementById('linked_entity_type').addEventListener('change', (e) => {
            this.loadLinkedEntities(e.target.value);
        });
        
        // Project change handler
        document.getElementById('project_id').addEventListener('change', (e) => {
            const linkedType = document.getElementById('linked_entity_type').value;
            if (linkedType) {
                this.loadLinkedEntities(linkedType);
            }
        });
    }

    async loadProjects() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/projects');
            
            if (response.status === 'success') {
                const select = document.getElementById('project_id');
                
                response.data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    
                    @if(isset($document))
                    if (project.id === {{ $document->project_id }}) {
                        option.selected = true;
                    }
                    @endif
                    
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    }

    async loadLinkedEntities(entityType) {
        const container = document.getElementById('linked-entity-container');
        const select = document.getElementById('linked_entity_id');
        const projectId = document.getElementById('project_id').value;
        
        if (!entityType || !projectId) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        select.innerHTML = '<option value="">Đang tải...</option>';
        
        try {
            let endpoint = '';
            switch (entityType) {
                case 'task':
                    endpoint = `/api/v1/projects/${projectId}/tasks`;
                    break;
                case 'diary':
                    endpoint = `/api/v1/projects/${projectId}/interaction-logs`;
                    break;
                case 'cr':
                    endpoint = `/api/v1/projects/${projectId}/change-requests`;
                    break;
            }
            
            const response = await zenaApp.apiCall('GET', endpoint);
            
            select.innerHTML = '<option value="">Chọn đối tượng</option>';
            
            if (response.status === 'success') {
                response.data.data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    
                    switch (entityType) {
                        case 'task':
                            option.textContent = item.name;
                            break;
                        case 'diary':
                            option.textContent = `${item.type} - ${item.description.substring(0, 50)}...`;
                            break;
                        case 'cr':
                            option.textContent = `${item.code} - ${item.title}`;
                            break;
                    }
                    
                    @if(isset($document))
                    if (item.id === {{ $document->linked_entity_id ?? 'null' }}) {
                        option.selected = true;
                    }
                    @endif
                    
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading linked entities:', error);
            select.innerHTML = '<option value="">Lỗi khi tải dữ liệu</option>';
        }
    }

    async loadVersionHistory() {
        const documentId = document.getElementById('document-id').value;
        const container = document.getElementById('version-history');
        
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/documents/${documentId}/versions`);
            
            if (response.status === 'success') {
                let html = '';
                
                response.data.forEach(version => {
                    html += `
                        <div class="version-item ${version.is_current ? 'current' : ''}">
                            <div class="version-header">
                                <strong>v${version.version_number}</strong>
                                ${version.is_current ? '<span class="badge badge-primary">Hiện tại</span>' : ''}
                            </div>
                            <div class="version-meta">
                                <small>${version.created_at}</small><br>
                                <small>Bởi: ${version.creator_name}</small>
                            </div>
                            ${version.comment ? `<div class="version-comment">${version.comment}</div>` : ''}
                            <div class="version-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadVersion(${version.id})">
                                    <i class="icon-download"></i>
                                </button>
                                ${!version.is_current ? `
                                    <button class="btn btn-sm btn-outline-secondary" onclick="revertToVersion(${version.id})">
                                        <i class="icon-refresh"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }
        } catch (error) {
            console.error('Error loading version history:', error);
            container.innerHTML = '<p class="text-muted">Không thể tải lịch sử phiên bản</p>';
        }
    }

    async saveDocument() {
        const form = document.getElementById('document-form');
        const formData = new FormData(form);
        
        // Show upload progress modal
        const modal = new bootstrap.Modal(document.getElementById('upload-progress-modal'));
        modal.show();
        
        try {
            let response;
            
            if (this.isEdit) {
                const documentId = document.getElementById('document-id').value;
                response = await this.uploadWithProgress('PUT', `/api/v1/documents/${documentId}`, formData);
            } else {
                response = await this.uploadWithProgress('POST', '/api/v1/documents', formData);
            }
            
            if (response.status === 'success') {
                modal.hide();
                zenaApp.showNotification(
                    this.isEdit ? 'Cập nhật tài liệu thành công' : 'Upload tài liệu thành công',
                    'success'
                );
                
                setTimeout(() => {
                    window.location.href = '/documents';
                }, 1500);
            }
        } catch (error) {
            modal.hide();
            zenaApp.showNotification(
                this.isEdit ? 'Lỗi khi cập nhật tài liệu' : 'Lỗi khi upload tài liệu',
                'error'
            );
        }
    }

    uploadWithProgress(method, url, formData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    document.getElementById('upload-progress-bar').style.width = percentComplete + '%';
                    document.getElementById('upload-status').textContent = 
                        `Đang upload... ${Math.round(percentComplete)}%`;
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(JSON.parse(xhr.responseText));
                } else {
                    reject(new Error('Upload failed'));
                }
            });
            
            xhr.addEventListener('error', () => {
                reject(new Error('Upload failed'));
            });
            
            xhr.open(method, url);
            xhr.setRequestHeader('Authorization', `Bearer ${localStorage.getItem('auth_token')}`);
            xhr.send(formData);
        });
    }
}

// Global functions
function downloadDocument(id) {
    window.open(`/api/v1/documents/${id}/download`, '_blank');
}

function downloadVersion(versionId) {
    window.open(`/api/v1/document-versions/${versionId}/download`, '_blank');
}

function viewDocument(id) {
    window.location.href = `/documents/${id}`;
}

async function revertToVersion(versionId) {
    if (!confirm('Bạn có muốn khôi phục về phiên bản này?')) {
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('POST', `/api/v1/document-versions/${versionId}/revert`);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Khôi phục phiên bản thành công', 'success');
            location.reload();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi khôi phục phiên bản', 'error');
    }
}

async function deleteDocument(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa tài liệu này?')) {
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('DELETE', `/api/v1/documents/${id}`);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Xóa tài liệu thành công', 'success');
            setTimeout(() => {
                window.location.href = '/documents';
            }, 1500);
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi xóa tài liệu', 'error');
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    new DocumentFormManager();
});
</script>

<style>
.version-item {
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
}

.version-item.current {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.version-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.version-meta {
    margin-bottom: 8px;
}

.version-comment {
    font-style: italic;
    margin-bottom: 8px;
    color: #6c757d;
}

.version-actions {
    display: flex;
    gap: 5px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #f0f0f0;
}

.stat-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.custom-file-label::after {
    content: "Chọn";
}
</style>
@endsection