<?php $__env->startSection('title', 'Projects'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50" x-data="projectsList()">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
                    <p class="text-sm text-gray-600">Manage your projects and track progress</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- View Mode Toggle -->
                    <div class="flex items-center bg-gray-100 rounded-lg p-1">
                        <button @click="setViewMode('table')" 
                                :class="viewMode === 'table' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded text-sm font-medium transition-all">
                            <i class="fas fa-table mr-1"></i>Table
                        </button>
                        <button @click="setViewMode('card')" 
                                :class="viewMode === 'card' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded text-sm font-medium transition-all">
                            <i class="fas fa-th-large mr-1"></i>Cards
                        </button>
                        <button @click="setViewMode('kanban')" 
                                :class="viewMode === 'kanban' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded text-sm font-medium transition-all">
                            <i class="fas fa-columns mr-1"></i>Kanban
                        </button>
                    </div>
                    
                    <a href="<?php echo e(route('app.projects.create')); ?>" 
                       data-testid="create-project"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>New Project
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                <!-- Search -->
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               x-model="filters.search"
                               @input.debounce.300ms="applyFilters()"
                               placeholder="Search projects..."
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center space-x-4">
                    <!-- Status Filter -->
                    <select x-model="filters.status" 
                            @change="applyFilters()"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Status</option>
                        <option value="planning">Planning</option>
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="archived">Archived</option>
                    </select>

                    <!-- Priority Filter -->
                    <select x-model="filters.priority" 
                            @change="applyFilters()"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>

                    <!-- Client Filter -->
                    <select x-model="filters.client_id" 
                            @change="applyFilters()"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Clients</option>
                        <?php if(isset($clients) && $clients->count() > 0): ?>
                            <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($client->id); ?>"><?php echo e($client->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </select>

                    <!-- Sort -->
                    <select x-model="filters.sort_by" 
                            @change="applyFilters()"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="name">Sort by Name</option>
                        <option value="status">Sort by Status</option>
                        <option value="priority">Sort by Priority</option>
                        <option value="start_date">Sort by Start Date</option>
                        <option value="end_date">Sort by End Date</option>
                        <option value="progress">Sort by Progress</option>
                        <option value="updated_at">Sort by Last Updated</option>
                    </select>

                    <!-- Clear Filters -->
                    <button @click="clearFilters()" 
                            class="px-3 py-2 text-gray-600 hover:text-gray-800 text-sm font-medium">
                        <i class="fas fa-times mr-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div x-show="selectedProjects.length > 0" 
         x-transition
         class="bg-blue-50 border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <span class="text-sm font-medium text-blue-900">
                        <span x-text="selectedProjects.length"></span> project(s) selected
                    </span>
                    <button @click="selectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                        Select All
                    </button>
                    <button @click="clearSelection()" class="text-sm text-blue-600 hover:text-blue-800">
                        Clear Selection
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="bulkAction('delete')" 
                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                    <button @click="bulkAction('archive')" 
                            class="px-3 py-1 bg-yellow-600 hover:bg-yellow-700 text-white text-sm rounded">
                        <i class="fas fa-archive mr-1"></i>Archive
                    </button>
                    <button @click="bulkAction('export')" 
                            class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Loading State -->
        <div x-show="loading" class="text-center py-12">
            <i class="fas fa-spinner fa-spin text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600">Loading projects...</p>
        </div>

        <!-- Table View -->
        <div x-show="viewMode === 'table'" x-transition>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" 
                                           @change="toggleSelectAll()"
                                           :checked="selectedProjects.length === filteredProjects.length && filteredProjects.length > 0"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Project
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Priority
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Progress
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Client
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Due Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="project in paginatedProjects" :key="project.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" 
                                               :value="project.id"
                                               @change="toggleProject(project.id)"
                                               :checked="selectedProjects.includes(project.id)"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <i class="fas fa-project-diagram text-blue-600"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900" x-text="project.name"></div>
                                                <div class="text-sm text-gray-500" x-text="project.description"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              :class="getStatusClass(project.status)"
                                              x-text="project.status"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                              :class="getPriorityClass(project.priority)"
                                              x-text="project.priority"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-blue-600 h-2 rounded-full" 
                                                     :style="`width: ${project.progress || 0}%`"></div>
                                            </div>
                                            <span class="text-sm text-gray-600" x-text="`${project.progress || 0}%`"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="project.client?.name || 'No Client'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatDate(project.end_date)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a :href="`/app/projects/${project.id}`" 
                                               class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a :href="`/app/projects/${project.id}/edit`" 
                                               class="text-indigo-600 hover:text-indigo-900">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button @click="deleteProject(project.id)" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Card View -->
        <div x-show="viewMode === 'card'" x-transition>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="project in paginatedProjects" :key="project.id">
                    <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           :value="project.id"
                                           @change="toggleProject(project.id)"
                                           :checked="selectedProjects.includes(project.id)"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                                    <h3 class="text-lg font-medium text-gray-900" x-text="project.name"></h3>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                      :class="getStatusClass(project.status)"
                                      x-text="project.status"></span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-4" x-text="project.description"></p>
                            
                            <div class="mb-4">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Progress</span>
                                    <span x-text="`${project.progress || 0}%`"></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" 
                                         :style="`width: ${project.progress || 0}%`"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <span x-text="formatDate(project.start_date)"></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-1"></i>
                                    <span x-text="project.client?.name || 'No Client'"></span>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                <a :href="`/app/projects/${project.id}`" 
                                   class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded text-sm font-medium text-center">
                                    View
                                </a>
                                <a :href="`/app/projects/${project.id}/edit`" 
                                   class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-2 rounded text-sm font-medium text-center">
                                    Edit
                                </a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Kanban View -->
        <div x-show="viewMode === 'kanban'" x-transition>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <template x-for="status in ['planning', 'active', 'on_hold', 'completed']" :key="status">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 capitalize" x-text="status.replace('_', ' ')"></h3>
                        <div class="space-y-3">
                            <template x-for="project in getProjectsByStatus(status)" :key="project.id">
                                <div class="bg-white rounded-lg shadow p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <input type="checkbox" 
                                               :value="project.id"
                                               @change="toggleProject(project.id)"
                                               :checked="selectedProjects.includes(project.id)"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              :class="getPriorityClass(project.priority)"
                                              x-text="project.priority"></span>
                                    </div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1" x-text="project.name"></h4>
                                    <p class="text-xs text-gray-600 mb-2" x-text="project.description"></p>
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span x-text="formatDate(project.end_date)"></span>
                                        <span x-text="`${project.progress || 0}%`"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Empty State -->
        <div x-show="!loading && filteredProjects.length === 0" class="text-center py-12">
            <i class="fas fa-project-diagram text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No projects found</h3>
            <p class="text-gray-500 mb-6">Try adjusting your filters or create a new project</p>
            <a href="<?php echo e(route('app.projects.create')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                <i class="fas fa-plus mr-2"></i>Create Project
            </a>
        </div>

        <!-- Pagination -->
        <div x-show="!loading && filteredProjects.length > 0" class="mt-8">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <span x-text="pagination.from"></span> to <span x-text="pagination.to"></span> 
                    of <span x-text="pagination.total"></span> results
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="previousPage()" 
                            :disabled="pagination.currentPage === 1"
                            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Previous
                    </button>
                    <span class="px-3 py-2 text-sm font-medium text-gray-700">
                        Page <span x-text="pagination.currentPage"></span> of <span x-text="pagination.lastPage"></span>
                    </span>
                    <button @click="nextPage()" 
                            :disabled="pagination.currentPage === pagination.lastPage"
                            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function projectsList() {
    return {
        loading: false,
        viewMode: '<?php echo e($viewMode ?? "table"); ?>',
        selectedProjects: [],
        filters: {
            search: '<?php echo e($filters["search"] ?? ""); ?>',
            status: '<?php echo e($filters["status"] ?? ""); ?>',
            priority: '<?php echo e($filters["priority"] ?? ""); ?>',
            client_id: '<?php echo e($filters["client_id"] ?? ""); ?>',
            sort_by: '<?php echo e($filters["sort_by"] ?? "name"); ?>',
            sort_direction: '<?php echo e($filters["sort_direction"] ?? "asc"); ?>'
        },
        projects: <?php echo json_encode($projects ?? [], 15, 512) ?>,
        meta: <?php echo json_encode($meta ?? [], 15, 512) ?>,
        pagination: {
            currentPage: <?php echo e($meta['current_page'] ?? 1); ?>,
            lastPage: <?php echo e($meta['last_page'] ?? 1); ?>,
            perPage: <?php echo e($meta['per_page'] ?? 15); ?>,
            total: <?php echo e($meta['total'] ?? 0); ?>,
            from: <?php echo e($meta['from'] ?? 0); ?>,
            to: <?php echo e($meta['to'] ?? 0); ?>

        },

        get filteredProjects() {
            let filtered = this.projects;

            // Apply search filter
            if (this.filters.search) {
                const search = this.filters.search.toLowerCase();
                filtered = filtered.filter(project => 
                    project.name.toLowerCase().includes(search) ||
                    project.description.toLowerCase().includes(search)
                );
            }

            // Apply status filter
            if (this.filters.status) {
                filtered = filtered.filter(project => project.status === this.filters.status);
            }

            // Apply priority filter
            if (this.filters.priority) {
                filtered = filtered.filter(project => project.priority === this.filters.priority);
            }

            // Apply client filter
            if (this.filters.client_id) {
                filtered = filtered.filter(project => project.client_id == this.filters.client_id);
            }

            // Apply sorting
            filtered.sort((a, b) => {
                const aVal = a[this.filters.sort_by] || '';
                const bVal = b[this.filters.sort_by] || '';
                
                if (this.filters.sort_direction === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });

            return filtered;
        },

        get paginatedProjects() {
            return this.filteredProjects;
        },

        setViewMode(mode) {
            this.viewMode = mode;
            this.updateViewModeSession(mode);
        },

        updateViewModeSession(mode) {
            fetch('/app/projects/view-mode', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ view_mode: mode })
            });
        },

        applyFilters() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) {
                    params.set(key, value);
                } else {
                    params.delete(key);
                }
            });
            params.set('page', '1');
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        },

        clearFilters() {
            this.filters = {
                search: '',
                status: '',
                priority: '',
                client_id: '',
                sort_by: 'name',
                sort_direction: 'asc'
            };
            this.applyFilters();
        },

        toggleProject(projectId) {
            const index = this.selectedProjects.indexOf(projectId);
            if (index > -1) {
                this.selectedProjects.splice(index, 1);
            } else {
                this.selectedProjects.push(projectId);
            }
        },

        toggleSelectAll() {
            if (this.selectedProjects.length === this.filteredProjects.length) {
                this.selectedProjects = [];
            } else {
                this.selectedProjects = this.filteredProjects.map(p => p.id);
            }
        },

        selectAll() {
            this.selectedProjects = this.filteredProjects.map(p => p.id);
        },

        clearSelection() {
            this.selectedProjects = [];
        },

        bulkAction(action) {
            if (this.selectedProjects.length === 0) return;

            const actionMap = {
                delete: () => this.bulkDelete(),
                archive: () => this.bulkArchive(),
                export: () => this.bulkExport()
            };

            if (actionMap[action]) {
                actionMap[action]();
            }
        },

        bulkDelete() {
            if (confirm(`Are you sure you want to delete ${this.selectedProjects.length} project(s)?`)) {
                this.performBulkAction('delete');
            }
        },

        bulkArchive() {
            if (confirm(`Are you sure you want to archive ${this.selectedProjects.length} project(s)?`)) {
                this.performBulkAction('archive');
            }
        },

        bulkExport() {
            this.performBulkAction('export');
        },

        async performBulkAction(action) {
            try {
                const response = await fetch('/app/projects/bulk-action', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        action: action,
                        project_ids: this.selectedProjects
                    })
                });

                const processedIds = [...this.selectedProjects];
                const result = await response.json();

                if (response.ok) {
                    this.showNotification('success', result.message || 'Action completed successfully');
                    if (action === 'delete') {
                        this.projects = this.projects.filter(p => !processedIds.includes(p.id));
                    } else if (action === 'archive') {
                        this.projects = this.projects.map(p => processedIds.includes(p.id) ? { ...p, status: 'archived' } : p);
                    }
                    this.selectedProjects = this.selectedProjects.filter(id => !processedIds.includes(id));
                } else {
                    this.showNotification('error', result.message || 'Failed to perform action');
                }
            } catch (error) {
                console.error('Bulk action error:', error);
                this.showNotification('error', 'An error occurred while performing the action');
            }
        },

        showNotification(type, message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        },

        async deleteProject(projectId) {
            if (confirm('Are you sure you want to delete this project?')) {
                try {
                    const response = await fetch(`/app/projects/${projectId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    if (response.ok) {
                        this.showNotification('success', 'Project deleted successfully');
                        // Remove project from local data
                        this.projects = this.projects.filter(p => p.id !== projectId);
                        // Clear selection if this project was selected
                        this.selectedProjects = this.selectedProjects.filter(id => id !== projectId);
                    } else {
                        const result = await response.json();
                        this.showNotification('error', result.message || 'Failed to delete project');
                    }
                } catch (error) {
                    console.error('Delete project error:', error);
                    this.showNotification('error', 'An error occurred while deleting the project');
                }
            }
        },

        getProjectsByStatus(status) {
            return this.filteredProjects.filter(project => project.status === status);
        },

        getStatusClass(status) {
            const classes = {
                'planning': 'bg-gray-100 text-gray-800',
                'active': 'bg-green-100 text-green-800',
                'on_hold': 'bg-yellow-100 text-yellow-800',
                'completed': 'bg-blue-100 text-blue-800',
                'cancelled': 'bg-red-100 text-red-800',
                'archived': 'bg-gray-200 text-gray-700'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        getPriorityClass(priority) {
            const classes = {
                'low': 'bg-gray-100 text-gray-800',
                'medium': 'bg-blue-100 text-blue-800',
                'high': 'bg-orange-100 text-orange-800',
                'urgent': 'bg-red-100 text-red-800'
            };
            return classes[priority] || 'bg-gray-100 text-gray-800';
        },

        formatDate(date) {
            if (!date) return 'No date';
            return new Date(date).toLocaleDateString();
        },

        previousPage() {
            if (this.pagination.currentPage > 1) {
                this.navigateToPage(this.pagination.currentPage - 1);
            }
        },

        nextPage() {
            if (this.pagination.currentPage < this.pagination.lastPage) {
                this.navigateToPage(this.pagination.currentPage + 1);
            }
        },

        navigateToPage(page) {
            const params = new URLSearchParams(window.location.search);
            params.set('page', page);
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        },
    }
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects/index.blade.php ENDPATH**/ ?>