@extends('layouts.app')

@section('title', 'Chi tiết Công việc')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" id="task-title">Chi tiết Công việc</h1>
        <div class="page-actions">
            <button class="btn btn-secondary" onclick="window.location.href='/tasks'">
                <i class="icon-arrow-left"></i> Quay lại danh sách
            </button>
            <button class="btn btn-primary" onclick="editTask()" id="edit-btn">
                <i class="icon-edit"></i> Chỉnh sửa
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-8">
            <!-- Task Overview -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Tổng quan</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Dự án:</label>
                                <span id="project-name">-</span>
                            </div>
                            <div class="info-item">
                                <label>Thành phần:</label>
                                <span id="component-name">-</span>
                            </div>
                            <div class="info-item">
                                <label>Trạng thái:</label>
                                <span id="task-status">-</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Ngày bắt đầu:</label>
                                <span id="start-date">-</span>
                            </div>
                            <div class="info-item">
                                <label>Ngày kết thúc:</label>
                                <span id="end-date">-</span>
                            </div>
                            <div class="info-item">
                                <label>Tiến độ:</label>
                                <div class="progress-display">
                                    <div class="progress">
                                        <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
                                    </div>
                                    <span id="progress-text">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item mt-3">
                        <label>Mô tả:</label>
                        <div id="task-description" class="description-content">-</div>
                    </div>
                    
                    <div class="info-item mt-3" id="conditional-tag-section" style="display: none;">
                        <label>Thẻ điều kiện:</label>
                        <span id="conditional-tag" class="badge badge-info">-</span>
                    </div>
                </div>
            </div>

            <!-- Task Assignments -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Phân công</h5>
                </div>
                <div class="card-body">
                    <div id="assignments-list">
                        <!-- Assignments will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <!-- Dependencies -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Phụ thuộc</h5>
                </div>
                <div class="card-body">
                    <div id="dependencies-list">
                        <!-- Dependencies will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <!-- Progress Updates -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Cập nhật tiến độ</h5>
                    <button class="btn btn-sm btn-primary" onclick="showProgressModal()">
                        <i class="icon-plus"></i> Cập nhật
                    </button>
                </div>
                <div class="card-body">
                    <div id="progress-updates">
                        <!-- Progress updates will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thao tác nhanh</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-success btn-block mb-2" onclick="markAsCompleted()" id="complete-btn">
                        <i class="icon-check"></i> Đánh dấu hoàn thành
                    </button>
                    <button class="btn btn-warning btn-block mb-2" onclick="markAsInProgress()" id="progress-btn">
                        <i class="icon-play"></i> Bắt đầu thực hiện
                    </button>
                    <button class="btn btn-secondary btn-block mb-2" onclick="markAsOnHold()" id="hold-btn">
                        <i class="icon-pause"></i> Tạm dừng
                    </button>
                    <button class="btn btn-outline-danger btn-block" onclick="deleteTask()">
                        <i class="icon-trash"></i> Xóa công việc
                    </button>
                </div>
            </div>

            <!-- Related Documents -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Tài liệu liên quan</h5>
                    <button class="btn btn-sm btn-primary" onclick="uploadDocument()">
                        <i class="icon-upload"></i> Tải lên
                    </button>
                </div>
                <div class="card-body">
                    <div id="related-documents">
                        <!-- Documents will be loaded via AJAX -->
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Hoạt động gần đây</h5>
                </div>
                <div class="card-body">
                    <div id="activity-timeline">
                        <!-- Activity timeline will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Update Modal -->
<div class="modal fade" id="progressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật tiến độ</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="progress-form">
                    <div class="form-group">
                        <label for="progress-percentage">Tiến độ (%) <span class="text-danger">*</span></label>
                        <input type="number" id="progress-percentage" name="progress" class="form-control" 
                               min="0" max="100" required>
                    </div>
                    <div class="form-group">
                        <label for="progress-note">Ghi chú</label>
                        <textarea id="progress-note" name="note" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveProgress()">Lưu</button>
            </div>
        </div>
    </div>
</div>

<script>
class TaskDetailManager {
    constructor() {
        this.taskId = {{ $taskId ?? 'null' }};
        this.taskData = null;
        
        if (this.taskId) {
            this.loadTaskDetail();
            this.loadAssignments();
            this.loadDependencies();
            this.loadProgressUpdates();
            this.loadRelatedDocuments();
            this.loadActivityTimeline();
        }
    }

    async loadTaskDetail() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/tasks/${this.taskId}`);
            
            if (response.status === 'success') {
                this.taskData = response.data;
                this.renderTaskDetail();
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải thông tin công việc', 'error');
        }
    }

    renderTaskDetail() {
        const task = this.taskData;
        
        document.getElementById('task-title').textContent = task.name;
        document.getElementById('project-name').textContent = task.project.name;
        document.getElementById('component-name').textContent = task.component ? task.component.name : 'Không có';
        document.getElementById('start-date').textContent = zenaApp.formatDate(task.start_date);
        document.getElementById('end-date').textContent = zenaApp.formatDate(task.end_date);
        document.getElementById('task-description').textContent = task.description || 'Không có mô tả';
        
        // Status
        const statusElement = document.getElementById('task-status');
        statusElement.innerHTML = `<span class="badge badge-${this.getStatusColor(task.status)}">${this.getStatusText(task.status)}</span>`;
        
        // Progress
        const progress = task.progress || 0;
        document.getElementById('progress-bar').style.width = `${progress}%`;
        document.getElementById('progress-text').textContent = `${progress}%`;
        
        // Conditional tag
        if (task.conditional_tag) {
            document.getElementById('conditional-tag-section').style.display = 'block';
            document.getElementById('conditional-tag').textContent = task.conditional_tag;
        }
        
        // Update action buttons based on status
        this.updateActionButtons(task.status);
    }

    updateActionButtons(status) {
        const completeBtn = document.getElementById('complete-btn');
        const progressBtn = document.getElementById('progress-btn');
        const holdBtn = document.getElementById('hold-btn');
        
        // Reset all buttons
        [completeBtn, progressBtn, holdBtn].forEach(btn => {
            btn.style.display = 'block';
            btn.disabled = false;
        });
        
        switch (status) {
            case 'completed':
                completeBtn.style.display = 'none';
                break;
            case 'in_progress':
                progressBtn.style.display = 'none';
                break;
            case 'on_hold':
                holdBtn.style.display = 'none';
                break;
        }
    }

    async loadAssignments() {
        const container = document.getElementById('assignments-list');
        
        if (!this.taskData || !this.taskData.assignments || this.taskData.assignments.length === 0) {
            container.innerHTML = '<p class="text-muted">Chưa có phân công nào</p>';
            return;
        }

        container.innerHTML = this.taskData.assignments.map(assignment => `
            <div class="assignment-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="assignee-info">
                        <h6 class="mb-1">${assignment.user.name}</h6>
                        <small class="text-muted">${assignment.user.email}</small>
                    </div>
                    <div class="assignment-percentage">
                        <span class="badge badge-primary">${assignment.split_percentage}%</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async loadDependencies() {
        const container = document.getElementById('dependencies-list');
        
        if (!this.taskData || !this.taskData.dependencies || this.taskData.dependencies.length === 0) {
            container.innerHTML = '<p class="text-muted">Không có phụ thuộc nào</p>';
            return;
        }

        try {
            const dependencyTasks = await Promise.all(
                this.taskData.dependencies.map(taskId => 
                    zenaApp.apiCall('GET', `/api/v1/tasks/${taskId}`)
                )
            );

            container.innerHTML = dependencyTasks.map(response => {
                if (response.status === 'success') {
                    const task = response.data;
                    return `
                        <div class="dependency-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="task-info">
                                    <h6 class="mb-1">${task.name}</h6>
                                    <small class="text-muted">${task.project.name}</small>
                                </div>
                                <div class="task-status">
                                    <span class="badge badge-${this.getStatusColor(task.status)}">
                                        ${this.getStatusText(task.status)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                }
                return '';
            }).join('');
        } catch (error) {
            container.innerHTML = '<p class="text-danger">Lỗi khi tải thông tin phụ thuộc</p>';
        }
    }

    async loadProgressUpdates() {
        // This would load progress update history
        const container = document.getElementById('progress-updates');
        container.innerHTML = '<p class="text-muted">Chưa có cập nhật tiến độ nào</p>';
    }

    async loadRelatedDocuments() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/tasks/${this.taskId}/documents`);
            const container = document.getElementById('related-documents');
            
            if (response.status === 'success' && response.data.length > 0) {
                container.innerHTML = response.data.map(doc => `
                    <div class="document-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="doc-info">
                                <h6 class="mb-1">${doc.title}</h6>
                                <small class="text-muted">v${doc.current_version.version_number}</small>
                            </div>
                            <div class="doc-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id})">
                                    <i class="icon-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">Chưa có tài liệu nào</p>';
            }
        } catch (error) {
            document.getElementById('related-documents').innerHTML = '<p class="text-muted">Chưa có tài liệu nào</p>';
        }
    }

    async loadActivityTimeline() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/tasks/${this.taskId}/activities`);
            const container = document.getElementById('activity-timeline');
            
            if (response.status === 'success' && response.data.length > 0) {
                container.innerHTML = response.data.map(activity => `
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">${activity.description}</h6>
                            <small class="text-muted">${zenaApp.formatDateTime(activity.created_at)}</small>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">Chưa có hoạt động nào</p>';
            }
        } catch (error) {
            document.getElementById('activity-timeline').innerHTML = '<p class="text-muted">Chưa có hoạt động nào</p>';
        }
    }

    async updateTaskStatus(status) {
        try {
            const response = await zenaApp.apiCall('PUT', `/api/v1/tasks/${this.taskId}`, { status });
            
            if (response.status === 'success') {
                zenaApp.showNotification('Cập nhật trạng thái thành công', 'success');
                this.loadTaskDetail();
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi cập nhật trạng thái', 'error');
        }
    }

    getStatusColor(status) {
        const colors = {
            'pending': 'warning',
            'in_progress': 'primary',
            'completed': 'success',
            'cancelled': 'danger',
            'on_hold': 'secondary'
        };
        return colors[status] || 'secondary';
    }

    getStatusText(status) {
        const texts = {
            'pending': 'Chờ thực hiện',
            'in_progress': 'Đang thực hiện',
            'completed': 'Hoàn thành',
            'cancelled': 'Đã hủy',
            'on_hold': 'Tạm dừng'
        };
        return texts[status] || status;
    }
}

// Global functions
function editTask() {
    window.location.href = `/tasks/${taskDetailManager.taskId}/edit`;
}

function markAsCompleted() {
    taskDetailManager.updateTaskStatus('completed');
}

function markAsInProgress() {
    taskDetailManager.updateTaskStatus('in_progress');
}

function markAsOnHold() {
    taskDetailManager.updateTaskStatus('on_hold');
}

function deleteTask() {
    if (confirm('Bạn có chắc chắn muốn xóa công việc này?')) {
        zenaApp.apiCall('DELETE', `/api/v1/tasks/${taskDetailManager.taskId}`)
            .then(response => {
                if (response.status === 'success') {
                    zenaApp.showNotification('Xóa công việc thành công', 'success');
                    window.location.href = '/tasks';
                }
            })
            .catch(error => {
                zenaApp.showNotification('Lỗi khi xóa công việc', 'error');
            });
    }
}

function showProgressModal() {
    $('#progressModal').modal('show');
}

function saveProgress() {
    const form = document.getElementById('progress-form');
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const formData = new FormData(form);
    const progressData = Object.fromEntries(formData.entries());

    zenaApp.apiCall('POST', `/api/v1/tasks/${taskDetailManager.taskId}/progress`, progressData)
        .then(response => {
            if (response.status === 'success') {
                zenaApp.showNotification('Cập nhật tiến độ thành công', 'success');
                $('#progressModal').modal('hide');
                taskDetailManager.loadTaskDetail();
                taskDetailManager.loadProgressUpdates();
            }
        })
        .catch(error => {
            zenaApp.showNotification('Lỗi khi cập nhật tiến độ', 'error');
        });
}

function uploadDocument() {
    // This would open a document upload modal
    zenaApp.showNotification('Chức năng upload tài liệu sẽ được triển khai', 'info');
}

function downloadDocument(documentId) {
    window.open(`/api/v1/documents/${documentId}/download`, '_blank');
}

// Initialize when page loads
let taskDetailManager;
document.addEventListener('DOMContentLoaded', function() {
    taskDetailManager = new TaskDetailManager();
});
</script>
@endsection