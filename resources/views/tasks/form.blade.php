@extends('layouts.app')

@section('title', isset($task) ? 'Chỉnh sửa Công việc' : 'Tạo Công việc mới')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">{{ isset($task) ? 'Chỉnh sửa Công việc' : 'Tạo Công việc mới' }}</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.history.back()">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thông tin cơ bản</h5>
                </div>
                <div class="card-body">
                    <form id="task-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="project_id">Dự án <span class="text-danger">*</span></label>
                                    <select id="project_id" name="project_id" class="form-control" required>
                                        <option value="">Chọn dự án</option>
                                        <!-- Projects will be loaded via AJAX -->
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="component_id">Thành phần</label>
                                    <select id="component_id" name="component_id" class="form-control">
                                        <option value="">Chọn thành phần</option>
                                        <!-- Components will be loaded based on selected project -->
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="name">Tên công việc <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Ngày bắt đầu <span class="text-danger">*</span></label>
                                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">Ngày kết thúc <span class="text-danger">*</span></label>
                                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Trạng thái</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="pending">Chờ thực hiện</option>
                                        <option value="in_progress">Đang thực hiện</option>
                                        <option value="completed">Hoàn thành</option>
                                        <option value="cancelled">Đã hủy</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="conditional_tag">Thẻ điều kiện</label>
                                    <input type="text" id="conditional_tag" name="conditional_tag" class="form-control" placeholder="Ví dụ: Material/Flooring">
                                    <small class="form-text text-muted">Thẻ để phân loại và điều kiện hiển thị công việc</small>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dependencies">Công việc phụ thuộc</label>
                            <select id="dependencies" name="dependencies[]" class="form-control" multiple>
                                <!-- Tasks will be loaded via AJAX -->
                            </select>
                            <small class="form-text text-muted">Chọn các công việc mà công việc này phụ thuộc vào</small>
                            <div class="invalid-feedback"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Task Assignments -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Phân công thực hiện</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addAssignment()">
                        <i class="icon-plus"></i> Thêm người thực hiện
                    </button>
                </div>
                <div class="card-body">
                    <div id="assignments-container">
                        <!-- Assignments will be added dynamically -->
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <button type="button" class="btn btn-primary btn-block" onclick="saveTask()">
                        <i class="icon-save"></i> {{ isset($task) ? 'Cập nhật' : 'Tạo mới' }}
                    </button>
                    @if(isset($task))
                    <button type="button" class="btn btn-outline-danger btn-block mt-2" onclick="deleteTask()">
                        <i class="icon-trash"></i> Xóa công việc
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class TaskFormManager {
    constructor() {
        this.taskId = {{ isset($task) ? $task->id : 'null' }};
        this.assignments = [];
        this.loadProjects();
        this.loadUsers();
        this.initializeEventListeners();
        
        @if(isset($task))
        this.loadTaskData();
        @endif
    }

    initializeEventListeners() {
        // Project change event to load components
        document.getElementById('project_id').addEventListener('change', (e) => {
            this.loadComponents(e.target.value);
            this.loadProjectTasks(e.target.value);
        });

        // Date validation
        document.getElementById('start_date').addEventListener('change', this.validateDates.bind(this));
        document.getElementById('end_date').addEventListener('change', this.validateDates.bind(this));
    }

    async loadProjects() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/projects?per_page=100');
            
            if (response.status === 'success') {
                const select = document.getElementById('project_id');
                response.data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách dự án:', error);
        }
    }

    async loadComponents(projectId) {
        const select = document.getElementById('component_id');
        select.innerHTML = '<option value="">Chọn thành phần</option>';
        
        if (!projectId) return;

        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${projectId}/components`);
            
            if (response.status === 'success') {
                response.data.forEach(component => {
                    const option = document.createElement('option');
                    option.value = component.id;
                    option.textContent = component.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách thành phần:', error);
        }
    }

    async loadProjectTasks(projectId) {
        const select = document.getElementById('dependencies');
        select.innerHTML = '';
        
        if (!projectId) return;

        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${projectId}/tasks`);
            
            if (response.status === 'success') {
                response.data.forEach(task => {
                    if (task.id !== this.taskId) { // Don't include current task
                        const option = document.createElement('option');
                        option.value = task.id;
                        option.textContent = task.name;
                        select.appendChild(option);
                    }
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách công việc:', error);
        }
    }

    async loadUsers() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/users?per_page=100');
            
            if (response.status === 'success') {
                this.users = response.data.data;
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách người dùng:', error);
        }
    }

    @if(isset($task))
    async loadTaskData() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/tasks/${this.taskId}`);
            
            if (response.status === 'success') {
                const task = response.data;
                
                // Fill form fields
                document.getElementById('project_id').value = task.project_id;
                document.getElementById('component_id').value = task.component_id || '';
                document.getElementById('name').value = task.name;
                document.getElementById('description').value = task.description || '';
                document.getElementById('start_date').value = task.start_date;
                document.getElementById('end_date').value = task.end_date;
                document.getElementById('status').value = task.status;
                document.getElementById('conditional_tag').value = task.conditional_tag || '';
                
                // Load components for selected project
                if (task.project_id) {
                    await this.loadComponents(task.project_id);
                    document.getElementById('component_id').value = task.component_id || '';
                    
                    await this.loadProjectTasks(task.project_id);
                    // Set dependencies
                    if (task.dependencies) {
                        const dependenciesSelect = document.getElementById('dependencies');
                        task.dependencies.forEach(depId => {
                            const option = dependenciesSelect.querySelector(`option[value="${depId}"]`);
                            if (option) option.selected = true;
                        });
                    }
                }
                
                // Load assignments
                if (task.assignments) {
                    this.assignments = task.assignments;
                    this.renderAssignments();
                }
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải thông tin công việc', 'error');
        }
    }
    @endif

    validateDates() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
            document.getElementById('end_date').setCustomValidity('Ngày kết thúc phải sau ngày bắt đầu');
        } else {
            document.getElementById('end_date').setCustomValidity('');
        }
    }

    addAssignment() {
        const assignment = {
            id: Date.now(), // Temporary ID for new assignments
            user_id: '',
            split_percentage: 100
        };
        
        this.assignments.push(assignment);
        this.renderAssignments();
    }

    removeAssignment(index) {
        this.assignments.splice(index, 1);
        this.renderAssignments();
        this.updatePercentages();
    }

    renderAssignments() {
        const container = document.getElementById('assignments-container');
        
        if (this.assignments.length === 0) {
            container.innerHTML = '<p class="text-muted">Chưa có người thực hiện nào</p>';
            return;
        }

        container.innerHTML = this.assignments.map((assignment, index) => `
            <div class="assignment-item mb-3 p-3 border rounded">
                <div class="form-group">
                    <label>Người thực hiện</label>
                    <select class="form-control" onchange="taskFormManager.updateAssignment(${index}, 'user_id', this.value)">
                        <option value="">Chọn người thực hiện</option>
                        ${this.users?.map(user => `
                            <option value="${user.id}" ${assignment.user_id == user.id ? 'selected' : ''}>
                                ${user.name}
                            </option>
                        `).join('') || ''}
                    </select>
                </div>
                <div class="form-group">
                    <label>Tỷ lệ phân công (%)</label>
                    <input type="number" class="form-control" min="1" max="100" 
                           value="${assignment.split_percentage}" 
                           onchange="taskFormManager.updateAssignment(${index}, 'split_percentage', this.value)">
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="taskFormManager.removeAssignment(${index})">
                    <i class="icon-trash"></i> Xóa
                </button>
            </div>
        `).join('');
    }

    updateAssignment(index, field, value) {
        this.assignments[index][field] = field === 'split_percentage' ? parseInt(value) : value;
        this.updatePercentages();
    }

    updatePercentages() {
        const total = this.assignments.reduce((sum, assignment) => sum + (assignment.split_percentage || 0), 0);
        
        if (total > 100) {
            zenaApp.showNotification('Tổng tỷ lệ phân công không được vượt quá 100%', 'warning');
        }
    }

    async saveTask() {
        const form = document.getElementById('task-form');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        // Validate assignments
        const totalPercentage = this.assignments.reduce((sum, assignment) => sum + (assignment.split_percentage || 0), 0);
        if (totalPercentage > 100) {
            zenaApp.showNotification('Tổng tỷ lệ phân công không được vượt quá 100%', 'error');
            return;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Add dependencies
        const dependencies = Array.from(document.getElementById('dependencies').selectedOptions).map(option => option.value);
        data.dependencies = dependencies;
        
        // Add assignments
        data.assignments = this.assignments.filter(assignment => assignment.user_id);

        try {
            const url = this.taskId ? `/api/v1/tasks/${this.taskId}` : '/api/v1/tasks';
            const method = this.taskId ? 'PUT' : 'POST';
            
            const response = await zenaApp.apiCall(method, url, data);
            
            if (response.status === 'success') {
                zenaApp.showNotification(
                    this.taskId ? 'Cập nhật công việc thành công' : 'Tạo công việc thành công', 
                    'success'
                );
                window.location.href = '/tasks';
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi lưu công việc', 'error');
        }
    }

    @if(isset($task))
    async deleteTask() {
        if (!confirm('Bạn có chắc chắn muốn xóa công việc này?')) {
            return;
        }

        try {
            const response = await zenaApp.apiCall('DELETE', `/api/v1/tasks/${this.taskId}`);
            
            if (response.status === 'success') {
                zenaApp.showNotification('Xóa công việc thành công', 'success');
                window.location.href = '/tasks';
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi xóa công việc', 'error');
        }
    }
    @endif
}

// Initialize when page loads
let taskFormManager;
document.addEventListener('DOMContentLoaded', function() {
    taskFormManager = new TaskFormManager();
});
</script>
@endsection