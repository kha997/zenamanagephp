@extends('layouts.app')

@section('title', 'Chi tiết Change Request')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" id="cr-title">Chi tiết Change Request</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.location.href='/change-requests'">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
            <button class="btn btn-primary" id="edit-cr-btn">
                <i class="icon-edit"></i> Chỉnh sửa
            </button>
            <div class="btn-group" id="approval-actions" style="display: none;">
                <button class="btn btn-success" onclick="approveCR()">
                    <i class="icon-check"></i> Phê duyệt
                </button>
                <button class="btn btn-danger" onclick="rejectCR()">
                    <i class="icon-x"></i> Từ chối
                </button>
            </div>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- CR Overview -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thông tin cơ bản</h5>
                </div>
                <div class="card-body" id="cr-overview">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
            
            <!-- Impact Analysis -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Phân tích tác động</h5>
                </div>
                <div class="card-body" id="impact-analysis">
                    <!-- Impact analysis will be loaded via AJAX -->
                </div>
            </div>
            
            <!-- Attached Documents -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Tài liệu đính kèm</h5>
                </div>
                <div class="card-body">
                    <div id="attached-documents">
                        <!-- Documents will be loaded via AJAX -->
                    </div>
                </div>
            </div>
            
            <!-- CR History -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Lịch sử thay đổi</h5>
                </div>
                <div class="card-body">
                    <div id="cr-history">
                        <!-- History will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- CR Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Trạng thái</h5>
                </div>
                <div class="card-body" id="cr-status">
                    <!-- Status will be loaded via AJAX -->
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Thao tác nhanh</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="duplicateCR()">
                            <i class="icon-copy"></i> Nhân bản CR
                        </button>
                        <button class="btn btn-outline-info" onclick="exportCR()">
                            <i class="icon-download"></i> Xuất PDF
                        </button>
                        <button class="btn btn-outline-success" onclick="linkToTask()">
                            <i class="icon-link"></i> Liên kết Task
                        </button>
                        <button class="btn btn-outline-warning" onclick="addComment()">
                            <i class="icon-message-circle"></i> Thêm ghi chú
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Related Items -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Mục liên quan</h5>
                </div>
                <div class="card-body" id="related-items">
                    <!-- Related items will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approval-modal-title">Phê duyệt Change Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approval-form">
                    <div class="mb-3">
                        <label class="form-label">Ghi chú quyết định:</label>
                        <textarea class="form-control" id="decision-note" rows="4" placeholder="Nhập ghi chú về quyết định..."></textarea>
                    </div>
                    <div class="mb-3" id="implementation-schedule" style="display: none;">
                        <label class="form-label">Lịch triển khai:</label>
                        <input type="date" class="form-control" id="implementation-date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirm-decision">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="comment-form">
                    <div class="mb-3">
                        <label class="form-label">Nội dung ghi chú:</label>
                        <textarea class="form-control" id="comment-content" rows="4" placeholder="Nhập ghi chú..."></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="internal-comment">
                            <label class="form-check-label" for="internal-comment">
                                Ghi chú nội bộ (không hiển thị cho khách hàng)
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveComment()">Lưu ghi chú</button>
            </div>
        </div>
    </div>
</div>

<script>
class ChangeRequestDetailManager {
    constructor() {
        this.crId = this.getCRIdFromUrl();
        this.currentCR = null;
        this.loadCRDetails();
    }

    getCRIdFromUrl() {
        const pathParts = window.location.pathname.split('/');
        return pathParts[pathParts.length - 1];
    }

    async loadCRDetails() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/change-requests/${this.crId}`);
            
            if (response.status === 'success') {
                this.currentCR = response.data;
                this.renderCRDetails(response.data);
                this.loadAttachedDocuments();
                this.loadCRHistory();
                this.loadRelatedItems();
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải thông tin Change Request', 'error');
        }
    }

    renderCRDetails(cr) {
        // Update page title
        document.getElementById('cr-title').textContent = `${cr.code} - ${cr.title}`;
        
        // Show/hide approval actions based on status and permissions
        if (cr.status === 'awaiting_approval' && this.canApprove()) {
            document.getElementById('approval-actions').style.display = 'block';
        }
        
        // Set edit button action
        document.getElementById('edit-cr-btn').onclick = () => {
            window.location.href = `/change-requests/${cr.id}/edit`;
        };
        
        // Render overview
        const overview = document.getElementById('cr-overview');
        overview.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Mã CR:</label>
                        <span class="fw-bold">${cr.code}</span>
                    </div>
                    <div class="info-item">
                        <label>Tiêu đề:</label>
                        <span>${cr.title}</span>
                    </div>
                    <div class="info-item">
                        <label>Dự án:</label>
                        <span><a href="/projects/${cr.project.id}">${cr.project.name}</a></span>
                    </div>
                    <div class="info-item">
                        <label>Mức độ ưu tiên:</label>
                        <span class="badge badge-${this.getPriorityColor(cr.priority)}">
                            ${this.getPriorityText(cr.priority)}
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Trạng thái:</label>
                        <span class="badge badge-${this.getStatusColor(cr.status)}">
                            ${this.getStatusText(cr.status)}
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Người tạo:</label>
                        <span>${cr.created_by.name}</span>
                    </div>
                    <div class="info-item">
                        <label>Ngày tạo:</label>
                        <span>${zenaApp.formatDateTime(cr.created_at)}</span>
                    </div>
                    ${cr.decided_by ? `
                        <div class="info-item">
                            <label>Người quyết định:</label>
                            <span>${cr.decided_by.name}</span>
                        </div>
                        <div class="info-item">
                            <label>Ngày quyết định:</label>
                            <span>${zenaApp.formatDateTime(cr.decided_at)}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
            ${cr.description ? `
                <div class="mt-3">
                    <label>Mô tả:</label>
                    <div class="description-content">${cr.description}</div>
                </div>
            ` : ''}
            ${cr.decision_note ? `
                <div class="mt-3">
                    <label>Ghi chú quyết định:</label>
                    <div class="alert alert-info">${cr.decision_note}</div>
                </div>
            ` : ''}
        `;
        
        // Render impact analysis
        this.renderImpactAnalysis(cr);
        
        // Render status sidebar
        this.renderStatusSidebar(cr);
    }

    renderImpactAnalysis(cr) {
        const impactContainer = document.getElementById('impact-analysis');
        impactContainer.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="impact-item">
                        <div class="impact-icon time">
                            <i class="icon-clock"></i>
                        </div>
                        <div class="impact-content">
                            <h6>Tác động thời gian</h6>
                            <span class="impact-value">${cr.impact_days || 0} ngày</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="impact-item">
                        <div class="impact-icon cost">
                            <i class="icon-dollar-sign"></i>
                        </div>
                        <div class="impact-content">
                            <h6>Tác động chi phí</h6>
                            <span class="impact-value">${zenaApp.formatCurrency(cr.impact_cost || 0)}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="impact-item">
                        <div class="impact-icon kpi">
                            <i class="icon-trending-up"></i>
                        </div>
                        <div class="impact-content">
                            <h6>Tác động KPI</h6>
                            <span class="impact-value">${this.formatKPIImpact(cr.impact_kpi)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderStatusSidebar(cr) {
        const statusContainer = document.getElementById('cr-status');
        statusContainer.innerHTML = `
            <div class="status-timeline">
                <div class="status-item ${cr.status === 'draft' ? 'active' : 'completed'}">
                    <div class="status-icon">
                        <i class="icon-edit-3"></i>
                    </div>
                    <div class="status-content">
                        <h6>Nháp</h6>
                        <small>Đang soạn thảo</small>
                    </div>
                </div>
                <div class="status-item ${cr.status === 'awaiting_approval' ? 'active' : (this.isStatusAfter(cr.status, 'awaiting_approval') ? 'completed' : '')}">
                    <div class="status-icon">
                        <i class="icon-clock"></i>
                    </div>
                    <div class="status-content">
                        <h6>Chờ phê duyệt</h6>
                        <small>Đang chờ quyết định</small>
                    </div>
                </div>
                <div class="status-item ${cr.status === 'approved' ? 'active completed' : (cr.status === 'rejected' ? 'active rejected' : '')}">
                    <div class="status-icon">
                        <i class="${cr.status === 'rejected' ? 'icon-x' : 'icon-check'}"></i>
                    </div>
                    <div class="status-content">
                        <h6>${cr.status === 'rejected' ? 'Từ chối' : 'Phê duyệt'}</h6>
                        <small>${cr.status === 'rejected' ? 'Đã bị từ chối' : 'Đã được phê duyệt'}</small>
                    </div>
                </div>
            </div>
        `;
    }

    async loadAttachedDocuments() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/change-requests/${this.crId}/documents`);
            
            if (response.status === 'success') {
                this.renderAttachedDocuments(response.data);
            }
        } catch (error) {
            console.error('Error loading attached documents:', error);
        }
    }

    renderAttachedDocuments(documents) {
        const container = document.getElementById('attached-documents');
        
        if (documents.length === 0) {
            container.innerHTML = '<p class="text-muted">Không có tài liệu đính kèm</p>';
            return;
        }
        
        container.innerHTML = documents.map(doc => `
            <div class="document-item">
                <div class="document-icon">
                    <i class="icon-file-text"></i>
                </div>
                <div class="document-info">
                    <h6><a href="/documents/${doc.id}">${doc.title}</a></h6>
                    <small class="text-muted">Phiên bản ${doc.current_version.version_number} • ${zenaApp.formatFileSize(doc.current_version.file_size)}</small>
                </div>
                <div class="document-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id})">
                        <i class="icon-download"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    async loadCRHistory() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/change-requests/${this.crId}/history`);
            
            if (response.status === 'success') {
                this.renderCRHistory(response.data);
            }
        } catch (error) {
            console.error('Error loading CR history:', error);
        }
    }

    renderCRHistory(history) {
        const container = document.getElementById('cr-history');
        
        if (history.length === 0) {
            container.innerHTML = '<p class="text-muted">Chưa có lịch sử thay đổi</p>';
            return;
        }
        
        container.innerHTML = history.map(item => `
            <div class="history-item">
                <div class="history-time">
                    <small class="text-muted">${zenaApp.formatDateTime(item.created_at)}</small>
                </div>
                <div class="history-content">
                    <strong>${item.user.name}</strong> ${item.action}
                    ${item.note ? `<div class="history-note">${item.note}</div>` : ''}
                </div>
            </div>
        `).join('');
    }

    async loadRelatedItems() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/change-requests/${this.crId}/related`);
            
            if (response.status === 'success') {
                this.renderRelatedItems(response.data);
            }
        } catch (error) {
            console.error('Error loading related items:', error);
        }
    }

    renderRelatedItems(items) {
        const container = document.getElementById('related-items');
        
        if (!items.tasks?.length && !items.documents?.length) {
            container.innerHTML = '<p class="text-muted">Không có mục liên quan</p>';
            return;
        }
        
        let html = '';
        
        if (items.tasks?.length) {
            html += `
                <div class="related-section">
                    <h6>Tasks liên quan</h6>
                    ${items.tasks.map(task => `
                        <div class="related-item">
                            <i class="icon-check-square"></i>
                            <a href="/tasks/${task.id}">${task.name}</a>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        if (items.documents?.length) {
            html += `
                <div class="related-section">
                    <h6>Tài liệu liên quan</h6>
                    ${items.documents.map(doc => `
                        <div class="related-item">
                            <i class="icon-file-text"></i>
                            <a href="/documents/${doc.id}">${doc.title}</a>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        container.innerHTML = html;
    }

    // Utility methods
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
            'draft': 'Nháp',
            'awaiting_approval': 'Chờ phê duyệt',
            'approved': 'Đã phê duyệt',
            'rejected': 'Đã từ chối'
        };
        return texts[status] || status;
    }

    getPriorityColor(priority) {
        const colors = {
            'low': 'success',
            'medium': 'warning',
            'high': 'danger',
            'critical': 'dark'
        };
        return colors[priority] || 'secondary';
    }

    getPriorityText(priority) {
        const texts = {
            'low': 'Thấp',
            'medium': 'Trung bình',
            'high': 'Cao',
            'critical': 'Khẩn cấp'
        };
        return texts[priority] || priority;
    }

    formatKPIImpact(kpiData) {
        if (!kpiData || typeof kpiData !== 'object') {
            return 'Không có';
        }
        
        const impacts = Object.entries(kpiData).map(([key, value]) => `${key}: ${value}`);
        return impacts.length > 0 ? impacts.join(', ') : 'Không có';
    }

    isStatusAfter(currentStatus, targetStatus) {
        const statusOrder = ['draft', 'awaiting_approval', 'approved'];
        return statusOrder.indexOf(currentStatus) > statusOrder.indexOf(targetStatus);
    }

    canApprove() {
        // Check if current user has permission to approve CRs
        return zenaApp.hasPermission('change_request.approve');
    }
}

// Global functions
function approveCR() {
    document.getElementById('approval-modal-title').textContent = 'Phê duyệt Change Request';
    document.getElementById('implementation-schedule').style.display = 'block';
    document.getElementById('confirm-decision').onclick = () => submitDecision('approved');
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function rejectCR() {
    document.getElementById('approval-modal-title').textContent = 'Từ chối Change Request';
    document.getElementById('implementation-schedule').style.display = 'none';
    document.getElementById('confirm-decision').onclick = () => submitDecision('rejected');
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

async function submitDecision(decision) {
    const note = document.getElementById('decision-note').value;
    const implementationDate = document.getElementById('implementation-date').value;
    
    try {
        const response = await zenaApp.apiCall('POST', `/api/v1/change-requests/${crDetailManager.crId}/decide`, {
            decision: decision,
            note: note,
            implementation_date: implementationDate
        });
        
        if (response.status === 'success') {
            zenaApp.showNotification(`Change Request đã được ${decision === 'approved' ? 'phê duyệt' : 'từ chối'}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
            crDetailManager.loadCRDetails(); // Reload to show updated status
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi xử lý quyết định', 'error');
    }
}

function duplicateCR() {
    window.location.href = `/change-requests/create?duplicate=${crDetailManager.crId}`;
}

function exportCR() {
    window.open(`/api/v1/change-requests/${crDetailManager.crId}/export`, '_blank');
}

function linkToTask() {
    // Open task linking modal or redirect to task creation with CR pre-filled
    window.location.href = `/tasks/create?change_request=${crDetailManager.crId}`;
}

function addComment() {
    new bootstrap.Modal(document.getElementById('commentModal')).show();
}

async function saveComment() {
    const content = document.getElementById('comment-content').value;
    const isInternal = document.getElementById('internal-comment').checked;
    
    if (!content.trim()) {
        zenaApp.showNotification('Vui lòng nhập nội dung ghi chú', 'warning');
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('POST', `/api/v1/change-requests/${crDetailManager.crId}/comments`, {
            content: content,
            is_internal: isInternal
        });
        
        if (response.status === 'success') {
            zenaApp.showNotification('Ghi chú đã được thêm', 'success');
            bootstrap.Modal.getInstance(document.getElementById('commentModal')).hide();
            document.getElementById('comment-form').reset();
            crDetailManager.loadCRHistory(); // Reload history to show new comment
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi thêm ghi chú', 'error');
    }
}

function downloadDocument(documentId) {
    window.open(`/api/v1/documents/${documentId}/download`, '_blank');
}

// Initialize when page loads
let crDetailManager;
document.addEventListener('DOMContentLoaded', function() {
    crDetailManager = new ChangeRequestDetailManager();
});
</script>

<style>
.info-item {
    margin-bottom: 1rem;
}

.info-item label {
    font-weight: 600;
    color: #6c757d;
    display: block;
    margin-bottom: 0.25rem;
}

.description-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    border-left: 4px solid #0d6efd;
}

.impact-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.impact-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.25rem;
    color: white;
}

.impact-icon.time {
    background: #17a2b8;
}

.impact-icon.cost {
    background: #ffc107;
}

.impact-icon.kpi {
    background: #28a745;
}

.impact-content h6 {
    margin: 0;
    font-size: 0.875rem;
    color: #6c757d;
}

.impact-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
}

.status-timeline {
    position: relative;
}

.status-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.status-item {
    position: relative;
    padding-left: 3rem;
    margin-bottom: 1.5rem;
}

.status-icon {
    position: absolute;
    left: 0;
    top: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    z-index: 1;
}

.status-item.completed .status-icon {
    background: #28a745;
    color: white;
}

.status-item.active .status-icon {
    background: #0d6efd;
    color: white;
}

.status-item.rejected .status-icon {
    background: #dc3545;
    color: white;
}

.status-content h6 {
    margin: 0;
    font-size: 0.875rem;
}

.status-content small {
    color: #6c757d;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
}

.document-icon {
    margin-right: 0.75rem;
    color: #6c757d;
}

.document-info {
    flex: 1;
}

.document-info h6 {
    margin: 0;
    font-size: 0.875rem;
}

.document-info h6 a {
    text-decoration: none;
    color: #0d6efd;
}

.document-info h6 a:hover {
    text-decoration: underline;
}

.history-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.history-item:last-child {
    border-bottom: none;
}

.history-time {
    margin-bottom: 0.25rem;
}

.history-note {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.related-section {
    margin-bottom: 1.5rem;
}

.related-section h6 {
    margin-bottom: 0.75rem;
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.related-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f3f4;
}

.related-item:last-child {
    border-bottom: none;
}

.related-item i {
    margin-right: 0.5rem;
    color: #6c757d;
    width: 16px;
}

.related-item a {
    text-decoration: none;
    color: #0d6efd;
    font-size: 0.875rem;
}

.related-item a:hover {
    text-decoration: underline;
}
</style>
@endsection