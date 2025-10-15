<?php $__env->startSection('title', 'Chi tiết Tài liệu - ' . $document->title); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo e($document->title); ?></h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.location.href='/documents'">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
            <button class="btn btn-outline-primary" onclick="editDocument(<?php echo e($document->id); ?>)">
                <i class="icon-edit"></i> Chỉnh sửa
            </button>
            <button class="btn btn-primary" onclick="downloadDocument(<?php echo e($document->id); ?>)">
                <i class="icon-download"></i> Tải xuống
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Document Overview -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thông tin tổng quan</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Dự án:</label>
                                <span class="info-value">
                                    <a href="/projects/<?php echo e($document->project->id); ?>"><?php echo e($document->project->name); ?></a>
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Phiên bản hiện tại:</label>
                                <span class="info-value">v<?php echo e($document->current_version->version_number ?? 1); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Định dạng:</label>
                                <span class="info-value">
                                    <?php echo e($document->current_version ? strtoupper(pathinfo($document->current_version->file_path, PATHINFO_EXTENSION)) : 'N/A'); ?>

                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Kích thước:</label>
                                <span class="info-value"><?php echo e($document->current_version ? formatFileSize($document->current_version->file_size) : 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <?php if($document->linked_entity_type && $document->linked_entity_id): ?>
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Liên kết với:</label>
                                <span class="info-value">
                                    <?php switch($document->linked_entity_type):
                                        case ('task'): ?>
                                            <a href="/tasks/<?php echo e($document->linked_entity_id); ?>">Công việc: <?php echo e($document->linked_entity->name ?? 'N/A'); ?></a>
                                            <?php break; ?>
                                        <?php case ('diary'): ?>
                                            <a href="/interaction-logs/<?php echo e($document->linked_entity_id); ?>">Nhật ký: <?php echo e($document->linked_entity->description ?? 'N/A'); ?></a>
                                            <?php break; ?>
                                        <?php case ('cr'): ?>
                                            <a href="/change-requests/<?php echo e($document->linked_entity_id); ?>">CR: <?php echo e($document->linked_entity->code ?? 'N/A'); ?></a>
                                            <?php break; ?>
                                        <?php default: ?>
                                            Không xác định
                                    <?php endswitch; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Tạo bởi:</label>
                                <span class="info-value"><?php echo e($document->creator->name ?? 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Ngày tạo:</label>
                                <span class="info-value"><?php echo e($document->created_at ? $document->created_at->format('d/m/Y H:i') : 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Cập nhật cuối:</label>
                                <span class="info-value"><?php echo e($document->updated_at ? $document->updated_at->format('d/m/Y H:i') : 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <?php if($document->description): ?>
                        <div class="col-md-12">
                            <div class="info-item">
                                <label>Mô tả:</label>
                                <div class="info-value description-text"><?php echo e($document->description); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Document Preview -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Xem trước tài liệu</h5>
                </div>
                <div class="card-body">
                    <div id="document-preview">
                        <!-- Preview will be loaded here -->
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
                        <!-- Version history will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Related Documents -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Tài liệu liên quan</h5>
                </div>
                <div class="card-body">
                    <div id="related-documents">
                        <!-- Related documents will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thao tác nhanh</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="downloadDocument(<?php echo e($document->id); ?>)">
                            <i class="icon-download"></i> Tải xuống phiên bản hiện tại
                        </button>
                        
                        <button class="btn btn-outline-info" onclick="editDocument(<?php echo e($document->id); ?>)">
                            <i class="icon-edit"></i> Chỉnh sửa thông tin
                        </button>
                        
                        <button class="btn btn-outline-secondary" onclick="showUploadNewVersionModal()">
                            <i class="icon-upload"></i> Upload phiên bản mới
                        </button>
                        
                        <button class="btn btn-outline-warning" onclick="shareDocument(<?php echo e($document->id); ?>)">
                            <i class="icon-share"></i> Chia sẻ tài liệu
                        </button>
                        
                        <button class="btn btn-outline-danger" onclick="deleteDocument(<?php echo e($document->id); ?>)">
                            <i class="icon-trash"></i> Xóa tài liệu
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Document Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Thống kê</h5>
                </div>
                <div class="card-body">
                    <div class="stat-item">
                        <label>Số lượt tải xuống:</label>
                        <span class="stat-value" id="download-count">0</span>
                    </div>
                    
                    <div class="stat-item">
                        <label>Số phiên bản:</label>
                        <span class="stat-value" id="version-count">0</span>
                    </div>
                    
                    <div class="stat-item">
                        <label>Lần xem cuối:</label>
                        <span class="stat-value" id="last-viewed">Chưa xem</span>
                    </div>
                </div>
            </div>
            
            <!-- Access Control -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Quyền truy cập</h5>
                </div>
                <div class="card-body">
                    <div id="access-control">
                        <!-- Access control will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload New Version Modal -->
<div class="modal fade" id="upload-new-version-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload phiên bản mới</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="new-version-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new-version-file" class="required">Chọn file mới</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="new-version-file" name="file" required>
                            <label class="custom-file-label" for="new-version-file">Chọn file...</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new-version-comment">Ghi chú phiên bản</label>
                        <textarea class="form-control" id="new-version-comment" name="comment" rows="3"
                                  placeholder="Mô tả những thay đổi trong phiên bản này..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Share Document Modal -->
<div class="modal fade" id="share-document-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chia sẻ tài liệu</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Link chia sẻ:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="share-link" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" onclick="copyShareLink()">
                                <i class="icon-copy"></i> Sao chép
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Quyền truy cập:</label>
                    <select class="form-control" id="share-permission">
                        <option value="view">Chỉ xem</option>
                        <option value="download">Xem và tải xuống</option>
                        <option value="edit">Chỉnh sửa</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Thời hạn:</label>
                    <select class="form-control" id="share-expiry">
                        <option value="">Không giới hạn</option>
                        <option value="1">1 ngày</option>
                        <option value="7">7 ngày</option>
                        <option value="30">30 ngày</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="generateShareLink()">Tạo link</button>
            </div>
        </div>
    </div>
</div>

<script>
class DocumentDetailManager {
    constructor() {
        this.documentId = <?php echo e($document->id); ?>;
        this.initializeComponents();
        this.loadDocumentData();
    }

    initializeComponents() {
        // Initialize new version upload form
        document.getElementById('new-version-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.uploadNewVersion();
        });
        
        // File input change handler
        document.getElementById('new-version-file').addEventListener('change', (e) => {
            const fileName = e.target.files[0]?.name || 'Chọn file...';
            e.target.nextElementSibling.textContent = fileName;
        });
    }

    async loadDocumentData() {
        await Promise.all([
            this.loadVersionHistory(),
            this.loadDocumentPreview(),
            this.loadRelatedDocuments(),
            this.loadDocumentStatistics(),
            this.loadAccessControl()
        ]);
    }

    async loadVersionHistory() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/documents/${this.documentId}/versions`);
            
            if (response.status === 'success') {
                this.renderVersionHistory(response.data);
                document.getElementById('version-count').textContent = response.data.length;
            }
        } catch (error) {
            console.error('Error loading version history:', error);
            document.getElementById('version-history').innerHTML = 
                '<p class="text-muted">Không thể tải lịch sử phiên bản</p>';
        }
    }

    renderVersionHistory(versions) {
        const container = document.getElementById('version-history');
        
        if (versions.length === 0) {
            container.innerHTML = '<p class="text-muted">Chưa có phiên bản nào</p>';
            return;
        }
        
        let html = '<div class="version-timeline">';
        
        versions.forEach((version, index) => {
            html += `
                <div class="version-item ${version.is_current ? 'current' : ''}">
                    <div class="version-marker">
                        <div class="version-number">v${version.version_number}</div>
                        ${version.is_current ? '<div class="current-badge">Hiện tại</div>' : ''}
                    </div>
                    <div class="version-content">
                        <div class="version-header">
                            <div class="version-meta">
                                <span class="version-date">${version.created_at}</span>
                                <span class="version-author">bởi ${version.creator_name}</span>
                            </div>
                            <div class="version-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadVersion(${version.id})" title="Tải xuống">
                                    <i class="icon-download"></i>
                                </button>
                                ${!version.is_current ? `
                                    <button class="btn btn-sm btn-outline-secondary" onclick="revertToVersion(${version.id})" title="Khôi phục">
                                        <i class="icon-refresh"></i>
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-outline-info" onclick="compareVersions(${version.id})" title="So sánh">
                                    <i class="icon-compare"></i>
                                </button>
                            </div>
                        </div>
                        ${version.comment ? `<div class="version-comment">${version.comment}</div>` : ''}
                        ${version.reverted_from_version_number ? `
                            <div class="version-note">
                                <i class="icon-info"></i> Khôi phục từ phiên bản ${version.reverted_from_version_number}
                            </div>
                        ` : ''}
                        <div class="version-details">
                            <span class="file-size">${this.formatFileSize(version.file_size)}</span>
                            <span class="file-type">${version.file_extension.toUpperCase()}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }

    async loadDocumentPreview() {
        const container = document.getElementById('document-preview');
        
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/documents/${this.documentId}/preview`);
            
            if (response.status === 'success') {
                if (response.data.preview_url) {
                    container.innerHTML = `
                        <div class="document-preview-container">
                            <iframe src="${response.data.preview_url}" class="document-preview-frame"></iframe>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="preview-not-available">
                            <i class="icon-file-text"></i>
                            <p>Không thể xem trước định dạng file này</p>
                            <button class="btn btn-primary" onclick="downloadDocument(${this.documentId})">
                                <i class="icon-download"></i> Tải xuống để xem
                            </button>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading document preview:', error);
            container.innerHTML = `
                <div class="preview-error">
                    <i class="icon-alert-triangle"></i>
                    <p>Lỗi khi tải xem trước tài liệu</p>
                </div>
            `;
        }
    }

    async loadRelatedDocuments() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/documents/${this.documentId}/related`);
            
            if (response.status === 'success') {
                this.renderRelatedDocuments(response.data);
            }
        } catch (error) {
            console.error('Error loading related documents:', error);
            document.getElementById('related-documents').innerHTML = 
                '<p class="text-muted">Không thể tải tài liệu liên quan</p>';
        }
    }

    renderRelatedDocuments(documents) {
        const container = document.getElementById('related-documents');
        
        if (documents.length === 0) {
            container.innerHTML = '<p class="text-muted">Không có tài liệu liên quan</p>';
            return;
        }
        
        let html = '<div class="related-documents-list">';
        
        documents.forEach(doc => {
            html += `
                <div class="related-document-item">
                    <div class="document-icon">
                        <i class="icon-file-${this.getFileIcon(doc.file_extension)}"></i>
                    </div>
                    <div class="document-info">
                        <div class="document-title">
                            <a href="/documents/${doc.id}">${doc.title}</a>
                        </div>
                        <div class="document-meta">
                            <span class="document-type">${doc.file_extension.toUpperCase()}</span>
                            <span class="document-size">${this.formatFileSize(doc.file_size)}</span>
                            <span class="document-date">${doc.updated_at}</span>
                        </div>
                    </div>
                    <div class="document-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id})">
                            <i class="icon-download"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }

    async loadDocumentStatistics() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/documents/${this.documentId}/statistics`);
            
            if (response.status === 'success') {
                document.getElementById('download-count').textContent = response.data.download_count || 0;
                document.getElementById('last-viewed').textContent = response.data.last_viewed || 'Chưa xem';
            }
        } catch (error) {
            console.error('Error loading document statistics:', error);
        }
    }

    async loadAccessControl() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/documents/${this.documentId}/access`);
            
            if (response.status === 'success') {
                this.renderAccessControl(response.data);
            }
        } catch (error) {
            console.error('Error loading access control:', error);
            document.getElementById('access-control').innerHTML = 
                '<p class="text-muted">Không thể tải thông tin quyền truy cập</p>';
        }
    }

    renderAccessControl(accessData) {
        const container = document.getElementById('access-control');
        
        let html = `
            <div class="access-level">
                <label>Mức độ truy cập:</label>
                <span class="badge badge-${this.getAccessBadgeClass(accessData.level)}">
                    ${this.getAccessLevelText(accessData.level)}
                </span>
            </div>
        `;
        
        if (accessData.shared_users && accessData.shared_users.length > 0) {
            html += '<div class="shared-users"><label>Đã chia sẻ với:</label><ul>';
            accessData.shared_users.forEach(user => {
                html += `<li>${user.name} (${user.permission})</li>`;
            });
            html += '</ul></div>';
        }
        
        container.innerHTML = html;
    }

    async uploadNewVersion() {
        const form = document.getElementById('new-version-form');
        const formData = new FormData(form);
        
        try {
            const response = await zenaApp.apiCall('POST', `/api/v1/documents/${this.documentId}/versions`, formData);
            
            if (response.status === 'success') {
                zenaApp.showNotification('Upload phiên bản mới thành công', 'success');
                
                // Close modal and reload data
                $('#upload-new-version-modal').modal('hide');
                form.reset();
                document.querySelector('#new-version-file + label').textContent = 'Chọn file...';
                
                // Reload version history and preview
                await this.loadVersionHistory();
                await this.loadDocumentPreview();
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi upload phiên bản mới', 'error');
        }
    }

    // Utility methods
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    getFileIcon(extension) {
        const iconMap = {
            'pdf': 'text',
            'doc': 'text', 'docx': 'text',
            'xls': 'text', 'xlsx': 'text',
            'ppt': 'text', 'pptx': 'text',
            'jpg': 'image', 'jpeg': 'image', 'png': 'image', 'gif': 'image',
            'zip': 'archive', 'rar': 'archive'
        };
        return iconMap[extension.toLowerCase()] || 'text';
    }

    getAccessBadgeClass(level) {
        const classMap = {
            'public': 'success',
            'internal': 'warning',
            'restricted': 'danger',
            'private': 'secondary'
        };
        return classMap[level] || 'secondary';
    }

    getAccessLevelText(level) {
        const textMap = {
            'public': 'Công khai',
            'internal': 'Nội bộ',
            'restricted': 'Hạn chế',
            'private': 'Riêng tư'
        };
        return textMap[level] || 'Không xác định';
    }
}

// Global functions
function editDocument(id) {
    window.location.href = `/documents/${id}/edit`;
}

function downloadDocument(id) {
    window.open(`/api/v1/documents/${id}/download`, '_blank');
}

function downloadVersion(versionId) {
    window.open(`/api/v1/document-versions/${versionId}/download`, '_blank');
}

function showUploadNewVersionModal() {
    $('#upload-new-version-modal').modal('show');
}

function shareDocument(id) {
    $('#share-document-modal').modal('show');
}

function generateShareLink() {
    const permission = document.getElementById('share-permission').value;
    const expiry = document.getElementById('share-expiry').value;
    
    // Generate share link logic here
    const shareLink = `${window.location.origin}/shared/documents/<?php echo e($document->id); ?>?token=generated_token`;
    document.getElementById('share-link').value = shareLink;
}

function copyShareLink() {
    const shareLink = document.getElementById('share-link');
    shareLink.select();
    document.execCommand('copy');
    zenaApp.showNotification('Đã sao chép link chia sẻ', 'success');
}

async function revertToVersion(versionId) {
    if (!confirm('Bạn có muốn khôi phục về phiên bản này? Điều này sẽ tạo một phiên bản mới.')) {
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

function compareVersions(versionId) {
    // Open version comparison modal or page
    window.open(`/documents/<?php echo e($document->id); ?>/versions/${versionId}/compare`, '_blank');
}

async function deleteDocument(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa tài liệu này? Hành động này không thể hoàn tác.')) {
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
    new DocumentDetailManager();
});
</script>

<style>
.info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.info-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.info-item label {
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 0;
}

.info-value {
    color: #495057;
}

.description-text {
    white-space: pre-wrap;
    max-width: 100%;
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

.version-timeline {
    position: relative;
}

.version-item {
    display: flex;
    margin-bottom: 20px;
    position: relative;
}

.version-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 20px;
    top: 50px;
    bottom: -20px;
    width: 2px;
    background-color: #e9ecef;
}

.version-marker {
    flex-shrink: 0;
    width: 40px;
    text-align: center;
    position: relative;
}

.version-number {
    background-color: #007bff;
    color: white;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.version-item.current .version-number {
    background-color: #28a745;
}

.current-badge {
    background-color: #28a745;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-top: 5px;
}

.version-content {
    flex: 1;
    margin-left: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.version-item.current .version-content {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.version-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.version-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.version-date {
    font-weight: 600;
    color: #495057;
}

.version-author {
    font-size: 14px;
    color: #6c757d;
}

.version-actions {
    display: flex;
    gap: 5px;
}

.version-comment {
    font-style: italic;
    color: #6c757d;
    margin-bottom: 10px;
    padding: 10px;
    background-color: rgba(255, 255, 255, 0.5);
    border-radius: 4px;
}

.version-note {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #856404;
    background-color: #fff3cd;
    padding: 8px;
    border-radius: 4px;
    margin-bottom: 10px;
    font-size: 14px;
}

.version-details {
    display: flex;
    gap: 15px;
    font-size: 14px;
    color: #6c757d;
}

.document-preview-container {
    width: 100%;
    height: 500px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    overflow: hidden;
}

.document-preview-frame {
    width: 100%;
    height: 100%;
    border: none;
}

.preview-not-available,
.preview-error {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.preview-not-available i,
.preview-error i {
    font-size: 48px;
    margin-bottom: 20px;
    display: block;
}

.related-documents-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.related-document-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    background-color: #f8f9fa;
}

.document-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #007bff;
    color: white;
    border-radius: 4px;
    margin-right: 15px;
}

.document-info {
    flex: 1;
}

.document-title a {
    font-weight: 600;
    color: #495057;
    text-decoration: none;
}

.document-title a:hover {
    color: #007bff;
    text-decoration: underline;
}

.document-meta {
    display: flex;
    gap: 10px;
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}

.document-actions {
    flex-shrink: 0;
}

.access-level {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.shared-users {
    margin-top: 15px;
}

.shared-users ul {
    list-style: none;
    padding: 0;
    margin: 5px 0 0 0;
}

.shared-users li {
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
}

.shared-users li:last-child {
    border-bottom: none;
}
</style>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/documents/show.blade.php ENDPATH**/ ?>