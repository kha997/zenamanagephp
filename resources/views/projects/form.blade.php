@extends('layouts.app')

@section('title', isset($project) ? 'Chỉnh sửa Dự án' : 'Tạo Dự án Mới')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">{{ isset($project) ? 'Chỉnh sửa Dự án' : 'Tạo Dự án Mới' }}</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.location.href='/projects'">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <form id="project-form" class="needs-validation" novalidate method="POST">
        @csrf
        @if(isset($project))
            @method('PUT')
            <input type="hidden" id="project-id" value="{{ $project->id }}">
        @endif
        
        <div class="row">
            <!-- Basic Information -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Thông tin cơ bản</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="name" class="required">Tên dự án</label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           value="{{ isset($project) ? $project->name : '' }}">
                                    <div class="invalid-feedback">Vui lòng nhập tên dự án</div>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description">Mô tả</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"
                                              placeholder="Mô tả chi tiết về dự án...">{{ isset($project) ? $project->description : '' }}</textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date" class="required">Ngày bắt đầu</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required
                                           value="{{ isset($project) ? $project->start_date : '' }}">
                                    <div class="invalid-feedback">Vui lòng chọn ngày bắt đầu</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date" class="required">Ngày kết thúc</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required
                                           value="{{ isset($project) ? $project->end_date : '' }}">
                                    <div class="invalid-feedback">Vui lòng chọn ngày kết thúc</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Trạng thái</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="planning" {{ (isset($project) && $project->status === 'planning') ? 'selected' : '' }}>Lập kế hoạch</option>
                                        <option value="active" {{ (isset($project) && $project->status === 'active') ? 'selected' : '' }}>Đang thực hiện</option>
                                        <option value="on_hold" {{ (isset($project) && $project->status === 'on_hold') ? 'selected' : '' }}>Tạm dừng</option>
                                        <option value="completed" {{ (isset($project) && $project->status === 'completed') ? 'selected' : '' }}>Hoàn thành</option>
                                        <option value="cancelled" {{ (isset($project) && $project->status === 'cancelled') ? 'selected' : '' }}>Đã hủy</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="planned_cost">Chi phí dự kiến</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="planned_cost" name="planned_cost" 
                                               step="0.01" min="0" value="{{ isset($project) ? $project->planned_cost : '' }}">
                                        <div class="input-group-append">
                                            <span class="input-group-text">VNĐ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Work Template Selection -->
                @if(!isset($project))
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">Mẫu công việc</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="work_template_id">Chọn mẫu công việc</label>
                            <select class="form-control" id="work_template_id" name="work_template_id">
                                <option value="">Không sử dụng mẫu</option>
                                <!-- Options will be loaded via AJAX -->
                            </select>
                            <small class="form-text text-muted">Chọn mẫu công việc để tự động tạo các task và component</small>
                        </div>
                        
                        <div id="template-preview" class="mt-3" style="display: none;">
                            <h6>Xem trước mẫu:</h6>
                            <div id="template-content"></div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Project Statistics -->
                @if(isset($project))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Thống kê dự án</h5>
                    </div>
                    <div class="card-body">
                        <div class="stat-item">
                            <label>Tiến độ:</label>
                            <div class="progress">
                                <div class="progress-bar" style="width: {{ $project->progress }}%"></div>
                            </div>
                            <span class="stat-value">{{ $project->progress }}%</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Chi phí thực tế:</label>
                            <span class="stat-value">{{ number_format($project->actual_cost) }} VNĐ</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Số lượng task:</label>
                            <span class="stat-value">{{ $project->tasks_count ?? 0 }}</span>
                        </div>
                        
                        <div class="stat-item">
                            <label>Số lượng component:</label>
                            <span class="stat-value">{{ $project->components_count ?? 0 }}</span>
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
                                <i class="icon-save"></i> {{ isset($project) ? 'Cập nhật' : 'Tạo dự án' }}
                            </button>
                            
                            @if(isset($project))
                            <button type="button" class="btn btn-outline-info" onclick="viewProject({{ $project->id }})">
                                <i class="icon-eye"></i> Xem chi tiết
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="duplicateProject()">
                                <i class="icon-copy"></i> Nhân bản dự án
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
class ProjectFormManager {
    constructor() {
        this.isEdit = document.getElementById('project-id') !== null;
        this.initializeForm();
        this.loadWorkTemplates();
    }

    initializeForm() {
        const form = document.getElementById('project-form');
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (form.checkValidity()) {
                this.saveProject();
            } else {
                form.classList.add('was-validated');
            }
        });
        
        // Date validation
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        
        startDate.addEventListener('change', () => {
            endDate.min = startDate.value;
        });
        
        endDate.addEventListener('change', () => {
            if (endDate.value < startDate.value) {
                endDate.setCustomValidity('Ngày kết thúc phải sau ngày bắt đầu');
            } else {
                endDate.setCustomValidity('');
            }
        });
        
        // Work template preview
        if (!this.isEdit) {
            document.getElementById('work_template_id').addEventListener('change', (e) => {
                this.previewTemplate(e.target.value);
            });
        }
    }

    async loadWorkTemplates() {
        if (this.isEdit) return;
        
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/work-templates');
            
            if (response.status === 'success') {
                const select = document.getElementById('work_template_id');
                
                response.data.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = `${template.name} (${template.category})`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading work templates:', error);
        }
    }

    async previewTemplate(templateId) {
        const previewDiv = document.getElementById('template-preview');
        const contentDiv = document.getElementById('template-content');
        
        if (!templateId) {
            previewDiv.style.display = 'none';
            return;
        }
        
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/work-templates/${templateId}`);
            
            if (response.status === 'success') {
                const template = response.data;
                const templateData = JSON.parse(template.template_data);
                
                let html = `<p><strong>Danh mục:</strong> ${template.category}</p>`;
                html += `<p><strong>Phiên bản:</strong> ${template.version}</p>`;
                
                if (templateData.tasks && templateData.tasks.length > 0) {
                    html += `<p><strong>Số lượng task:</strong> ${templateData.tasks.length}</p>`;
                    html += '<ul>';
                    templateData.tasks.slice(0, 5).forEach(task => {
                        html += `<li>${task.name}</li>`;
                    });
                    if (templateData.tasks.length > 5) {
                        html += `<li>... và ${templateData.tasks.length - 5} task khác</li>`;
                    }
                    html += '</ul>';
                }
                
                contentDiv.innerHTML = html;
                previewDiv.style.display = 'block';
            }
        } catch (error) {
            console.error('Error previewing template:', error);
        }
    }

    async saveProject() {
        const formData = new FormData(document.getElementById('project-form'));
        const data = Object.fromEntries(formData.entries());
        
        try {
            let response;
            
            if (this.isEdit) {
                const projectId = document.getElementById('project-id').value;
                response = await zenaApp.apiCall('PUT', `/api/v1/projects/${projectId}`, data);
            } else {
                response = await zenaApp.apiCall('POST', '/api/v1/projects', data);
            }
            
            if (response.status === 'success') {
                zenaApp.showNotification(
                    this.isEdit ? 'Cập nhật dự án thành công' : 'Tạo dự án thành công',
                    'success'
                );
                
                setTimeout(() => {
                    window.location.href = `/projects/${response.data.id}`;
                }, 1500);
            }
        } catch (error) {
            zenaApp.showNotification(
                this.isEdit ? 'Lỗi khi cập nhật dự án' : 'Lỗi khi tạo dự án',
                'error'
            );
        }
    }
}

// Global functions
function viewProject(id) {
    window.location.href = `/projects/${id}`;
}

async function duplicateProject() {
    if (!confirm('Bạn có muốn nhân bản dự án này?')) {
        return;
    }
    
    try {
        const projectId = document.getElementById('project-id').value;
        const response = await zenaApp.apiCall('POST', `/api/v1/projects/${projectId}/duplicate`);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Nhân bản dự án thành công', 'success');
            setTimeout(() => {
                window.location.href = `/projects/${response.data.id}/edit`;
            }, 1500);
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi nhân bản dự án', 'error');
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    new ProjectFormManager();
});
</script>
@endsection