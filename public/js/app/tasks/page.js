function tasksPage() {
    return {
        // State
        loading: false,
        error: null,
        tasks: [],
        projects: [],
        users: [],
        selectedTasks: [],
        
        // Pagination
        meta: {
            total: 0,
            per_page: 15,
            current_page: 1,
            last_page: 1,
            from: 0,
            to: 0
        },
        
        // Filters
        filters: {
            q: '',
            project_id: '',
            assignee_id: '',
            status: '',
            priority: '',
            tag: '',
            due: '',
            range: '',
            sort: '-created_at',
            page: 1,
            per_page: 15
        },
        
        // Modals
        showCreateModal: false,
        showEditModal: false,
        showBulkModal: false,
        editingTask: null,
        
        // URL state
        urlParams: new URLSearchParams(window.location.search),
        
        // Initialize
        async init() {
            console.log('[TasksPage] Initializing...');
            
            // Load URL parameters
            this.loadUrlParams();
            
            // Load initial data
            await this.loadProjects();
            await this.loadUsers();
            await this.loadTasks();
            
            console.log('[TasksPage] Initialization complete');
        },
        
        // URL Management
        loadUrlParams() {
            this.filters.q = this.urlParams.get('q') || '';
            this.filters.project_id = this.urlParams.get('project_id') || '';
            this.filters.assignee_id = this.urlParams.get('assignee_id') || '';
            this.filters.status = this.urlParams.get('status') || '';
            this.filters.priority = this.urlParams.get('priority') || '';
            this.filters.tag = this.urlParams.get('tag') || '';
            this.filters.due = this.urlParams.get('due') || '';
            this.filters.range = this.urlParams.get('range') || '';
            this.filters.sort = this.urlParams.get('sort') || '-created_at';
            this.filters.page = parseInt(this.urlParams.get('page')) || 1;
            this.filters.per_page = parseInt(this.urlParams.get('per_page')) || 15;
        },
        
        updateUrl() {
            const params = new URLSearchParams();
            
            Object.keys(this.filters).forEach(key => {
                if (this.filters[key] && this.filters[key] !== '') {
                    params.set(key, this.filters[key]);
                }
            });
            
            const newUrl = `${window.location.pathname}?${params.toString()}`;
            window.history.pushState({}, '', newUrl);
        },
        
        // Data Loading
        async loadTasks() {
            this.loading = true;
            this.error = null;
            
            try {
                const params = new URLSearchParams();
                Object.keys(this.filters).forEach(key => {
                    if (this.filters[key] && this.filters[key] !== '') {
                        params.set(key, this.filters[key]);
                    }
                });
                
                const response = await fetch(`/api/test/tasks?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.tasks = data.data;
                    this.meta = data.meta;
                    this.updateUrl();
                } else {
                    throw new Error(data.message || 'Failed to load tasks');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error loading tasks:', error);
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        
        async loadProjects() {
            try {
                const response = await fetch('/api/test/projects', {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success') {
                        this.projects = data.data;
                    }
                }
            } catch (error) {
                console.error('[TasksPage] Error loading projects:', error);
            }
        },
        
        async loadUsers() {
            try {
                const response = await fetch('/api/app/users', {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success') {
                        this.users = data.data;
                    }
                }
            } catch (error) {
                console.error('[TasksPage] Error loading users:', error);
            }
        },
        
        // Filter Management
        applyFilters() {
            this.filters.page = 1; // Reset to first page
            this.loadTasks();
        },
        
        hasActiveFilters() {
            return Object.keys(this.filters).some(key => 
                key !== 'page' && key !== 'per_page' && key !== 'sort' && 
                this.filters[key] && this.filters[key] !== ''
            );
        },
        
        getActiveFilterChips() {
            const chips = [];
            
            if (this.filters.q) {
                chips.push({ key: 'q', label: `Search: ${this.filters.q}` });
            }
            if (this.filters.project_id) {
                const project = this.projects.find(p => p.id === this.filters.project_id);
                chips.push({ key: 'project_id', label: `Project: ${project?.name || this.filters.project_id}` });
            }
            if (this.filters.status) {
                chips.push({ key: 'status', label: `Status: ${this.getStatusLabel(this.filters.status)}` });
            }
            if (this.filters.priority) {
                chips.push({ key: 'priority', label: `Priority: ${this.getPriorityLabel(this.filters.priority)}` });
            }
            
            return chips;
        },
        
        removeFilter(key) {
            this.filters[key] = '';
            this.applyFilters();
        },
        
        clearFilters() {
            this.filters = {
                q: '',
                project_id: '',
                assignee_id: '',
                status: '',
                priority: '',
                tag: '',
                due: '',
                range: '',
                sort: '-created_at',
                page: 1,
                per_page: 15
            };
            this.applyFilters();
        },
        
        // Task Selection
        toggleSelectAll() {
            if (this.selectedTasks.length === this.tasks.length) {
                this.selectedTasks = [];
            } else {
                this.selectedTasks = this.tasks.map(task => task.id);
            }
        },
        
        toggleTaskSelection(taskId) {
            const index = this.selectedTasks.indexOf(taskId);
            if (index > -1) {
                this.selectedTasks.splice(index, 1);
            } else {
                this.selectedTasks.push(taskId);
            }
        },
        
        // Pagination
        getPageNumbers() {
            const current = this.meta.current_page || 1;
            const last = this.meta.last_page || 1;
            const pages = [];
            
            // Always show first page
            if (current > 3) {
                pages.push(1);
                if (current > 4) pages.push('...');
            }
            
            // Show pages around current
            const start = Math.max(1, current - 2);
            const end = Math.min(last, current + 2);
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            
            // Always show last page
            if (current < last - 2) {
                if (current < last - 3) pages.push('...');
                pages.push(last);
            }
            
            return pages;
        },
        
        goToPage(page) {
            if (page !== '...' && page >= 1 && page <= this.meta.last_page) {
                this.filters.page = page;
                this.loadTasks();
            }
        },
        
        previousPage() {
            if (this.meta.current_page > 1) {
                this.filters.page = this.meta.current_page - 1;
                this.loadTasks();
            }
        },
        
        nextPage() {
            if (this.meta.current_page < this.meta.last_page) {
                this.filters.page = this.meta.current_page + 1;
                this.loadTasks();
            }
        },
        
        // Task Actions
        async createTask(taskData) {
            try {
                const response = await fetch('/api/app/tasks', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify(taskData)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast('Task created successfully!', 'success');
                    this.showCreateModal = false;
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to create task');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error creating task:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        async updateTask(taskId, taskData) {
            try {
                const response = await fetch(`/api/app/tasks/${taskId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify(taskData)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast('Task updated successfully!', 'success');
                    this.showEditModal = false;
                    this.editingTask = null;
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to update task');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error updating task:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        async deleteTask(task) {
            if (!confirm(`Are you sure you want to delete "${task.title}"?`)) {
                return;
            }
            
            try {
                const response = await fetch(`/api/app/tasks/${task.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast('Task deleted successfully!', 'success');
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to delete task');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error deleting task:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        // Quick Actions
        async updateTaskStatus(taskId, status) {
            try {
                const response = await fetch(`/api/app/tasks/${taskId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify({ status })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast('Task status updated!', 'success');
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to update task status');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error updating task status:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        async assignTask(taskId, assigneeId) {
            try {
                const response = await fetch(`/api/app/tasks/${taskId}/assign`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify({ assignee_id: assigneeId })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast('Task assigned successfully!', 'success');
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to assign task');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error assigning task:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        // Bulk Actions
        async bulkUpdateStatus(status) {
            if (this.selectedTasks.length === 0) return;
            
            try {
                const response = await fetch('/api/app/tasks/bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify({
                        action: 'status',
                        ids: this.selectedTasks,
                        payload: { status }
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast(`Updated ${data.data.summary.success} tasks!`, 'success');
                    this.selectedTasks = [];
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to update tasks');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error bulk updating tasks:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        async bulkAssign(assigneeId) {
            if (this.selectedTasks.length === 0) return;
            
            try {
                const response = await fetch('/api/app/tasks/bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify({
                        action: 'assign',
                        ids: this.selectedTasks,
                        payload: { assignee_id: assigneeId }
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast(`Assigned ${data.data.summary.success} tasks!`, 'success');
                    this.selectedTasks = [];
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to assign tasks');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error bulk assigning tasks:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        async bulkDelete() {
            if (this.selectedTasks.length === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${this.selectedTasks.length} tasks?`)) {
                return;
            }
            
            try {
                const response = await fetch('/api/app/tasks/bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        ids: this.selectedTasks,
                        payload: {}
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    this.showToast(`Deleted ${data.data.summary.success} tasks!`, 'success');
                    this.selectedTasks = [];
                    await this.loadTasks();
                } else {
                    throw new Error(data.message || 'Failed to delete tasks');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error bulk deleting tasks:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        // Export
        async exportTasks() {
            try {
                const params = new URLSearchParams();
                Object.keys(this.filters).forEach(key => {
                    if (this.filters[key] && this.filters[key] !== '') {
                        params.set(key, this.filters[key]);
                    }
                });
                
                const response = await fetch(`/api/app/tasks/export?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('access_token') || ''}`
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Download the CSV file
                    const blob = new Blob([data.data.content], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    this.showToast('Tasks exported successfully!', 'success');
                } else {
                    throw new Error(data.message || 'Failed to export tasks');
                }
                
            } catch (error) {
                console.error('[TasksPage] Error exporting tasks:', error);
                this.showToast(error.message, 'error');
            }
        },
        
        // UI Helpers
        editTask(task) {
            this.editingTask = task;
            this.showEditModal = true;
        },
        
        getStatusClass(status) {
            const classes = {
                'backlog': 'bg-gray-100 text-gray-800',
                'in_progress': 'bg-blue-100 text-blue-800',
                'blocked': 'bg-red-100 text-red-800',
                'done': 'bg-green-100 text-green-800',
                'canceled': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },
        
        getStatusLabel(status) {
            const labels = {
                'backlog': 'Backlog',
                'in_progress': 'In Progress',
                'blocked': 'Blocked',
                'done': 'Done',
                'canceled': 'Canceled'
            };
            return labels[status] || status;
        },
        
        getPriorityClass(priority) {
            const classes = {
                'low': 'bg-green-100 text-green-800',
                'normal': 'bg-blue-100 text-blue-800',
                'high': 'bg-yellow-100 text-yellow-800',
                'urgent': 'bg-red-100 text-red-800'
            };
            return classes[priority] || 'bg-gray-100 text-gray-800';
        },
        
        getPriorityLabel(priority) {
            const labels = {
                'low': 'Low',
                'normal': 'Normal',
                'high': 'High',
                'urgent': 'Urgent'
            };
            return labels[priority] || priority;
        },
        
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },
        
        formatRelativeTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
            
            return date.toLocaleDateString();
        },
        
        showToast(message, type = 'info') {
            // Simple toast implementation
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                'bg-blue-500'
            }`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 3000);
        }
    }
}
