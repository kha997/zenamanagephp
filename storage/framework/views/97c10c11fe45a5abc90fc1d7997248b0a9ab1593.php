<?php $__env->startSection('title', $changeRequest ? 'Chỉnh sửa Yêu cầu Thay đổi' : 'Tạo Yêu cầu Thay đổi'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo e($changeRequest ? 'Chỉnh sửa' : 'Tạo'); ?> Yêu cầu Thay đổi</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.location.href='/change-requests'">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <form id="change-request-form" method="POST">
        <?php echo csrf_field(); ?>
        <?php if(isset($changeRequest)): ?>
            <?php echo method_field('PUT'); ?>
        <?php endif; ?>
        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Thông tin cơ bản</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project-select">Dự án <span class="text-danger">*</span></label>
                                    <select id="project-select" class="form-control" required>
                                        <option value="">Chọn dự án...</option>
                                        <!-- Projects will be loaded via AJAX -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="code-input">Mã CR</label>
                                    <input type="text" id="code-input" class="form-control" placeholder="Tự động tạo nếu để trống" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="title-input">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" id="title-input" class="form-control" placeholder="Nhập tiêu đề yêu cầu thay đổi..." required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description-input">Mô tả chi tiết <span class="text-danger">*</span></label>
                            <textarea id="description-input" class="form-control" rows="6" placeholder="Mô tả chi tiết về yêu cầu thay đổi, lý do, và các yếu tố liên quan..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority-select">Mức độ ưu tiên</label>
                            <select id="priority-select" class="form-control">
                                <option value="low">Thấp</option>
                                <option value="medium" selected>Trung bình</option>
                                <option value="high">Cao</option>
                                <option value="critical">Khẩn cấp</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Impact Analysis -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Phân tích tác động</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="impact-days">Tác động thời gian (ngày)</label>
                                    <input type="number" id="impact-days" class="form-control" min="0" placeholder="0">
                                    <small class="form-text text-muted">Số ngày gia hạn dự kiến</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="impact-cost">Tác động chi phí (VND)</label>
                                    <input type="number" id="impact-cost" class="form-control" min="0" placeholder="0">
                                    <small class="form-text text-muted">Chi phí phát sinh dự kiến</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="impact-kpi">Tác động KPI</label>
                            <div id="kpi-impacts">
                                <div class="kpi-impact-item">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control kpi-name" placeholder="Tên KPI">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control kpi-current" placeholder="Giá trị hiện tại" step="0.01">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control kpi-expected" placeholder="Giá trị dự kiến" step="0.01">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKpiImpact(this)">
                                                <i class="icon-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addKpiImpact()">
                                <i class="icon-plus"></i> Thêm KPI
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Attachments -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Tài liệu đính kèm</h5>
                    </div>
                    <div class="card-body">
                        <div class="upload-area" id="upload-area">
                            <div class="upload-placeholder">
                                <i class="icon-upload"></i>
                                <p>Kéo thả file vào đây hoặc <a href="#" onclick="document.getElementById('file-input').click()">chọn file</a></p>
                                <small class="text-muted">Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Tối đa 10MB mỗi file)</small>
                            </div>
                            <input type="file" id="file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display: none;">
                        </div>
                        
                        <div id="uploaded-files" class="mt-3">
                            <!-- Uploaded files will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Current Info (Edit mode only) -->
                <div class="card" id="current-info-card" style="display: none;">
                    <div class="card-header">
                        <h5 class="card-title">Thông tin hiện tại</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <label>Trạng thái:</label>
                            <span id="current-status" class="badge"></span>
                        </div>
                        <div class="info-item">
                            <label>Người tạo:</label>
                            <span id="current-creator"></span>
                        </div>
                        <div class="info-item">
                            <label>Ngày tạo:</label>
                            <span id="current-created-at"></span>
                        </div>
                        <div class="info-item" id="decision-info" style="display: none;">
                            <label>Người quyết định:</label>
                            <span id="current-decided-by"></span>
                            <label>Ngày quyết định:</label>
                            <span id="current-decided-at"></span>
                            <label>Ghi chú quyết định:</label>
                            <p id="current-decision-note"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Thao tác nhanh</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" onclick="saveChangeRequest()">
                                <i class="icon-save"></i> Lưu bản nháp
                            </button>
                            <button type="button" class="btn btn-success" onclick="submitForApproval()">
                                <i class="icon-send"></i> Gửi phê duyệt
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="previewChangeRequest()">
                                <i class="icon-eye"></i> Xem trước
                            </button>
                            <div id="edit-actions" style="display: none;">
                                <button type="button" class="btn btn-outline-info" onclick="viewChangeRequest()">
                                    <i class="icon-external-link"></i> Xem chi tiết
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="duplicateChangeRequest()">
                                    <i class="icon-copy"></i> Nhân bản
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Related Items -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Liên kết</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="related-tasks">Công việc liên quan:</label>
                            <select id="related-tasks" class="form-control" multiple>
                                <!-- Tasks will be loaded based on selected project -->
                            </select>
                            <small class="form-text text-muted">Chọn các công việc bị ảnh hưởng</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="related-documents">Tài liệu liên quan:</label>
                            <select id="related-documents" class="form-control" multiple>
                                <!-- Documents will be loaded based on selected project -->
                            </select>
                            <small class="form-text text-muted">Chọn tài liệu tham khảo</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xem trước Yêu cầu Thay đổi</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="preview-content">
                <!-- Preview content will be generated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="$('#previewModal').modal('hide'); saveChangeRequest();">Lưu</button>
            </div>
        </div>
    </div>
</div>

<script>
class ChangeRequestFormManager {
    constructor() {
        this.changeRequestId = this.getChangeRequestIdFromUrl();
        this.isEditMode = !!this.changeRequestId;
        this.uploadedFiles = [];
        this.kpiImpacts = [];
        
        this.initializeForm();
        this.loadProjects();
        this.initializeEventListeners();
        
        if (this.isEditMode) {
            this.loadChangeRequest();
        }
    }
    
    getChangeRequestIdFromUrl() {
        const pathParts = window.location.pathname.split('/');
        const editIndex = pathParts.indexOf('edit');
        return editIndex > 0 ? parseInt(pathParts[editIndex - 1]) : null;
    }
    
    initializeForm() {
        if (this.isEditMode) {
            document.getElementById('current-info-card').style.display = 'block';
            document.getElementById('edit-actions').style.display = 'block';
        }
    }
    
    initializeEventListeners() {
        // Project selection change
        document.getElementById('project-select').addEventListener('change', (e) => {
            if (e.target.value) {
                this.loadProjectRelatedData(e.target.value);
            }
        });
        
        // File upload
        document.getElementById('file-input').addEventListener('change', (e) => {
            this.handleFileUpload(e.target.files);
        });
        
        // Drag and drop
        const uploadArea = document.getElementById('upload-area');
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            this.handleFileUpload(e.dataTransfer.files);
        });
    }
    
    async loadProjects() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/projects?per_page=100');
            
            if (response.status === 'success') {
                const projectSelect = document.getElementById('project-select');
                const projects = response.data.data;
                
                projects.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    projectSelect.appendChild(option);
                });
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải danh sách dự án', 'error');
        }
    }
    
    async loadProjectRelatedData(projectId) {
        try {
            // Load tasks
            const tasksResponse = await zenaApp.apiCall('GET', `/api/v1/projects/${projectId}/tasks`);
            if (tasksResponse.status === 'success') {
                const tasksSelect = document.getElementById('related-tasks');
                tasksSelect.innerHTML = '';
                
                tasksResponse.data.forEach(task => {
                    const option = document.createElement('option');
                    option.value = task.id;
                    option.textContent = task.name;
                    tasksSelect.appendChild(option);
                });
            }
            
            // Load documents
            const docsResponse = await zenaApp.apiCall('GET', `/api/v1/projects/${projectId}/documents`);
            if (docsResponse.status === 'success') {
                const docsSelect = document.getElementById('related-documents');
                docsSelect.innerHTML = '';
                
                docsResponse.data.forEach(doc => {
                    const option = document.createElement('option');
                    option.value = doc.id;
                    option.textContent = doc.title;
                    docsSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải dữ liệu liên quan:', error);
        }
    }
    
    async loadChangeRequest() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/change-requests/${this.changeRequestId}`);
            
            if (response.status === 'success') {
                const cr = response.data;
                
                // Fill form fields
                document.getElementById('project-select').value = cr.project_id;
                document.getElementById('code-input').value = cr.code;
                document.getElementById('title-input').value = cr.title;
                document.getElementById('description-input').value = cr.description;
                document.getElementById('priority-select').value = cr.priority || 'medium';
                document.getElementById('impact-days').value = cr.impact_days || '';
                document.getElementById('impact-cost').value = cr.impact_cost || '';
                
                // Load KPI impacts
                if (cr.impact_kpi) {
                    this.loadKpiImpacts(cr.impact_kpi);
                }
                
                // Fill current info
                document.getElementById('current-status').textContent = this.getStatusText(cr.status);
                document.getElementById('current-status').className = `badge badge-${this.getStatusColor(cr.status)}`;
                document.getElementById('current-creator').textContent = cr.creator ? cr.creator.name : 'N/A';
                document.getElementById('current-created-at').textContent = zenaApp.formatDate(cr.created_at);
                
                if (cr.decided_by) {
                    document.getElementById('decision-info').style.display = 'block';
                    document.getElementById('current-decided-by').textContent = cr.decided_by.name;
                    document.getElementById('current-decided-at').textContent = zenaApp.formatDate(cr.decided_at);
                    document.getElementById('current-decision-note').textContent = cr.decision_note || 'Không có ghi chú';
                }
                
                // Load project related data
                if (cr.project_id) {
                    await this.loadProjectRelatedData(cr.project_id);
                }
                
                // Set related items
                if (cr.related_tasks) {
                    const tasksSelect = document.getElementById('related-tasks');
                    cr.related_tasks.forEach(taskId => {
                        const option = tasksSelect.querySelector(`option[value="${taskId}"]`);
                        if (option) option.selected = true;
                    });
                }
                
                if (cr.related_documents) {
                    const docsSelect = document.getElementById('related-documents');
                    cr.related_documents.forEach(docId => {
                        const option = docsSelect.querySelector(`option[value="${docId}"]`);
                        if (option) option.selected = true;
                    });
                }
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải thông tin yêu cầu thay đổi', 'error');
        }
    }
    
    loadKpiImpacts(kpiData) {
        const container = document.getElementById('kpi-impacts');
        container.innerHTML = '';
        
        if (Array.isArray(kpiData)) {
            kpiData.forEach(kpi => {
                this.addKpiImpactItem(kpi.name, kpi.current_value, kpi.expected_value);
            });
        }
        
        if (container.children.length === 0) {
            this.addKpiImpactItem();
        }
    }
    
    addKpiImpactItem(name = '', currentValue = '', expectedValue = '') {
        const container = document.getElementById('kpi-impacts');
        const item = document.createElement('div');
        item.className = 'kpi-impact-item mb-2';
        item.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <input type="text" class="form-control kpi-name" placeholder="Tên KPI" value="${name}">
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control kpi-current" placeholder="Giá trị hiện tại" step="0.01" value="${currentValue}">
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control kpi-expected" placeholder="Giá trị dự kiến" step="0.01" value="${expectedValue}">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKpiImpact(this)">
                        <i class="icon-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(item);
    }
    
    handleFileUpload(files) {
        Array.from(files).forEach(file => {
            if (this.validateFile(file)) {
                this.uploadFile(file);
            }
        });
    }
    
    validateFile(file) {
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!allowedTypes.includes(file.type)) {
            zenaApp.showNotification(`File ${file.name} không được hỗ trợ`, 'warning');
            return false;
        }
        
        if (file.size > maxSize) {
            zenaApp.showNotification(`File ${file.name} quá lớn (tối đa 10MB)`, 'warning');
            return false;
        }
        
        return true;
    }
    
    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await zenaApp.apiCall('POST', '/api/v1/files/upload', formData, {
                'Content-Type': 'multipart/form-data'
            });
            
            if (response.status === 'success') {
                this.uploadedFiles.push(response.data);
                this.renderUploadedFiles();
            }
        } catch (error) {
            zenaApp.showNotification(`Lỗi khi upload file ${file.name}`, 'error');
        }
    }
    
    renderUploadedFiles() {
        const container = document.getElementById('uploaded-files');
        container.innerHTML = this.uploadedFiles.map(file => `
            <div class="uploaded-file-item" data-file-id="${file.id}">
                <div class="file-info">
                    <i class="icon-file"></i>
                    <span class="file-name">${file.original_name}</span>
                    <span class="file-size">(${zenaApp.formatFileSize(file.size)})</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeUploadedFile('${file.id}')">
                    <i class="icon-trash"></i>
                </button>
            </div>
        `).join('');
    }
    
    collectFormData() {
        // Collect KPI impacts
        const kpiImpacts = [];
        document.querySelectorAll('.kpi-impact-item').forEach(item => {
            const name = item.querySelector('.kpi-name').value;
            const currentValue = item.querySelector('.kpi-current').value;
            const expectedValue = item.querySelector('.kpi-expected').value;
            
            if (name.trim()) {
                kpiImpacts.push({
                    name: name.trim(),
                    current_value: parseFloat(currentValue) || 0,
                    expected_value: parseFloat(expectedValue) || 0
                });
            }
        });
        
        // Collect related items
        const relatedTasks = Array.from(document.getElementById('related-tasks').selectedOptions).map(option => parseInt(option.value));
        const relatedDocuments = Array.from(document.getElementById('related-documents').selectedOptions).map(option => parseInt(option.value));
        
        return {
            project_id: parseInt(document.getElementById('project-select').value),
            title: document.getElementById('title-input').value.trim(),
            description: document.getElementById('description-input').value.trim(),
            priority: document.getElementById('priority-select').value,
            impact_days: parseInt(document.getElementById('impact-days').value) || null,
            impact_cost: parseFloat(document.getElementById('impact-cost').value) || null,
            impact_kpi: kpiImpacts.length > 0 ? kpiImpacts : null,
            related_tasks: relatedTasks,
            related_documents: relatedDocuments,
            attachments: this.uploadedFiles.map(file => file.id)
        };
    }
    
    validateForm(data) {
        if (!data.project_id) {
            zenaApp.showNotification('Vui lòng chọn dự án', 'warning');
            return false;
        }
        
        if (!data.title) {
            zenaApp.showNotification('Vui lòng nhập tiêu đề', 'warning');
            return false;
        }
        
        if (!data.description) {
            zenaApp.showNotification('Vui lòng nhập mô tả', 'warning');
            return false;
        }
        
        return true;
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
function addKpiImpact() {
    changeRequestFormManager.addKpiImpactItem();
}

function removeKpiImpact(button) {
    const item = button.closest('.kpi-impact-item');
    if (document.querySelectorAll('.kpi-impact-item').length > 1) {
        item.remove();
    } else {
        // Clear the last item instead of removing it
        item.querySelectorAll('input').forEach(input => input.value = '');
    }
}

function removeUploadedFile(fileId) {
    changeRequestFormManager.uploadedFiles = changeRequestFormManager.uploadedFiles.filter(file => file.id !== fileId);
    changeRequestFormManager.renderUploadedFiles();
}

async function saveChangeRequest() {
    const data = changeRequestFormManager.collectFormData();
    
    if (!changeRequestFormManager.validateForm(data)) {
        return;
    }
    
    data.status = 'draft';
    
    try {
        let response;
        if (changeRequestFormManager.isEditMode) {
            response = await zenaApp.apiCall('PUT', `/api/v1/change-requests/${changeRequestFormManager.changeRequestId}`, data);
        } else {
            response = await zenaApp.apiCall('POST', '/api/v1/change-requests', data);
        }
        
        if (response.status === 'success') {
            zenaApp.showNotification('Lưu yêu cầu thay đổi thành công', 'success');
            
            if (!changeRequestFormManager.isEditMode) {
                window.location.href = `/change-requests/${response.data.id}/edit`;
            }
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi lưu yêu cầu thay đổi', 'error');
    }
}

async function submitForApproval() {
    const data = changeRequestFormManager.collectFormData();
    
    if (!changeRequestFormManager.validateForm(data)) {
        return;
    }
    
    data.status = 'awaiting_approval';
    
    if (!confirm('Bạn có chắc chắn muốn gửi yêu cầu này để phê duyệt? Sau khi gửi, bạn sẽ không thể chỉnh sửa.')) {
        return;
    }
    
    try {
        let response;
        if (changeRequestFormManager.isEditMode) {
            response = await zenaApp.apiCall('PUT', `/api/v1/change-requests/${changeRequestFormManager.changeRequestId}`, data);
        } else {
            response = await zenaApp.apiCall('POST', '/api/v1/change-requests', data);
        }
        
        if (response.status === 'success') {
            zenaApp.showNotification('Gửi yêu cầu phê duyệt thành công', 'success');
            window.location.href = `/change-requests/${response.data.id}`;
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi gửi yêu cầu phê duyệt', 'error');
    }
}

function previewChangeRequest() {
    const data = changeRequestFormManager.collectFormData();
    
    if (!changeRequestFormManager.validateForm(data)) {
        return;
    }
    
    // Generate preview content
    const previewContent = `
        <div class="change-request-preview">
            <h4>${data.title}</h4>
            <div class="row">
                <div class="col-md-6">
                    <strong>Dự án:</strong> ${document.getElementById('project-select').selectedOptions[0]?.text || 'N/A'}
                </div>
                <div class="col-md-6">
                    <strong>Mức độ ưu tiên:</strong> <span class="badge badge-info">${document.getElementById('priority-select').selectedOptions[0]?.text}</span>
                </div>
            </div>
            <div class="mt-3">
                <strong>Mô tả:</strong>
                <p>${data.description.replace(/\n/g, '<br>')}</p>
            </div>
            ${data.impact_days || data.impact_cost ? `
                <div class="mt-3">
                    <strong>Tác động:</strong>
                    <ul>
                        ${data.impact_days ? `<li>Thời gian: ${data.impact_days} ngày</li>` : ''}
                        ${data.impact_cost ? `<li>Chi phí: ${zenaApp.formatCurrency(data.impact_cost)}</li>` : ''}
                    </ul>
                </div>
            ` : ''}
            ${data.impact_kpi && data.impact_kpi.length > 0 ? `
                <div class="mt-3">
                    <strong>Tác động KPI:</strong>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>KPI</th>
                                <th>Hiện tại</th>
                                <th>Dự kiến</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.impact_kpi.map(kpi => `
                                <tr>
                                    <td>${kpi.name}</td>
                                    <td>${kpi.current_value}</td>
                                    <td>${kpi.expected_value}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('preview-content').innerHTML = previewContent;
    $('#previewModal').modal('show');
}

function viewChangeRequest() {
    if (changeRequestFormManager.changeRequestId) {
        window.location.href = `/change-requests/${changeRequestFormManager.changeRequestId}`;
    }
}

function duplicateChangeRequest() {
    if (confirm('Bạn có muốn tạo một yêu cầu thay đổi mới dựa trên yêu cầu hiện tại?')) {
        // Clear the ID to create a new CR
        changeRequestFormManager.changeRequestId = null;
        changeRequestFormManager.isEditMode = false;
        
        // Clear some fields
        document.getElementById('code-input').value = '';
        document.getElementById('current-info-card').style.display = 'none';
        document.getElementById('edit-actions').style.display = 'none';
        
        // Update page title
        document.querySelector('.page-title').textContent = 'Tạo Yêu cầu Thay đổi';
        document.title = 'Tạo Yêu cầu Thay đổi';
        
        zenaApp.showNotification('Đã tạo bản sao. Vui lòng kiểm tra và lưu lại.', 'info');
    }
}

// Initialize when page loads
let changeRequestFormManager;
document.addEventListener('DOMContentLoaded', () => {
    changeRequestFormManager = new ChangeRequestFormManager();
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/change-requests/form.blade.php ENDPATH**/ ?>