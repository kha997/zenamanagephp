<?php $__env->startSection('title', 'Projects'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
                    <p class="mt-1 text-sm text-gray-600">Manage your projects and track progress</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="exportProjects()" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-download mr-2"></i>
                        Export
                    </button>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.create')): ?>
                        <a href="<?php echo e(route('app.projects.create')); ?>" 
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus mr-2"></i>
                            New Project
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filter Bar -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Search -->
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Search projects..."
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   onkeyup="handleSearch(event)">
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" 
                                name="status" 
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                onchange="handleFilterChange()">
                            <option value="">All Status</option>
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Owner Filter -->
                    <div>
                        <label for="owner" class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                        <select id="owner" 
                                name="owner" 
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                onchange="handleFilterChange()">
                            <option value="">All Owners</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div>
                        <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <select id="date_range" 
                                name="date_range" 
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                onchange="handleFilterChange()">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <button onclick="clearFilters()" 
                                class="text-sm text-gray-500 hover:text-gray-700">
                            Clear filters
                        </button>
                        <span id="filter-count" class="text-sm text-gray-500"></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="refreshProjects()" 
                                class="text-sm text-blue-600 hover:text-blue-500">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Projects</h2>
                    <div class="flex items-center space-x-2">
                        <span id="projects-count" class="text-sm text-gray-500">0 projects</span>
                        <div class="flex items-center space-x-1">
                            <button onclick="toggleView('table')" 
                                    id="view-table-btn"
                                    class="p-2 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md">
                                <i class="fas fa-table"></i>
                            </button>
                            <button onclick="toggleView('grid')" 
                                    id="view-grid-btn"
                                    class="p-2 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div id="table-view" class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" 
                                           id="select-all" 
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                           onchange="toggleSelectAll()">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button onclick="sortBy('name')" class="flex items-center space-x-1 hover:text-gray-700">
                                        <span>Name</span>
                                        <i class="fas fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button onclick="sortBy('status')" class="flex items-center space-x-1 hover:text-gray-700">
                                        <span>Status</span>
                                        <i class="fas fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button onclick="sortBy('owner')" class="flex items-center space-x-1 hover:text-gray-700">
                                        <span>Owner</span>
                                        <i class="fas fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button onclick="sortBy('progress')" class="flex items-center space-x-1 hover:text-gray-700">
                                        <span>Progress</span>
                                        <i class="fas fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button onclick="sortBy('due_date')" class="flex items-center space-x-1 hover:text-gray-700">
                                        <span>Due Date</span>
                                        <i class="fas fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="projects-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- Loading skeleton -->
                            <tr class="animate-pulse">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="h-4 w-4 bg-gray-200 rounded"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="h-4 bg-gray-200 rounded w-32"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="h-4 bg-gray-200 rounded w-20"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="h-4 bg-gray-200 rounded w-24"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="h-4 bg-gray-200 rounded w-16"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="h-4 bg-gray-200 rounded w-20"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="h-4 bg-gray-200 rounded w-16"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grid View -->
            <div id="grid-view" class="hidden p-6">
                <div id="projects-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Loading skeleton -->
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="hidden p-12 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-project-diagram text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No projects found</h3>
                <p class="text-gray-500 mb-4">Get started by creating your first project.</p>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.create')): ?>
                    <a href="<?php echo e(route('app.projects.create')); ?>" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>
                        Create First Project
                    </a>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <div id="pagination" class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-700">
                        <span id="pagination-info">Showing 0 to 0 of 0 results</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="goToPage(1)" 
                                id="first-page-btn"
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            First
                        </button>
                        <button onclick="goToPage(currentPage - 1)" 
                                id="prev-page-btn"
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Previous
                        </button>
                        <span id="page-numbers" class="flex items-center space-x-1">
                            <!-- Page numbers will be generated here -->
                        </span>
                        <button onclick="goToPage(currentPage + 1)" 
                                id="next-page-btn"
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                        <button onclick="goToPage(totalPages)" 
                                id="last-page-btn"
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Last
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Project Actions Modal -->
<div id="project-actions-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-modal-backdrop">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Project Actions</h3>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-3">
                    <button onclick="viewProject()" 
                            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                        <i class="fas fa-eye mr-3 text-gray-400"></i>
                        View Details
                    </button>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.edit')): ?>
                        <button onclick="editProject()" 
                                class="w-full flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                            <i class="fas fa-edit mr-3 text-gray-400"></i>
                            Edit Project
                        </button>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.delete')): ?>
                        <button onclick="deleteProject()" 
                                class="w-full flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50 rounded-md">
                            <i class="fas fa-trash mr-3 text-red-400"></i>
                            Delete Project
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                <button onclick="closeProjectActions()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
// Projects management
class ProjectsManager {
    constructor() {
        this.projects = [];
        this.filteredProjects = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.pageSize = 10;
        this.sortField = 'updated_at';
        this.sortOrder = 'desc';
        this.filters = {};
        this.selectedProjects = [];
        this.currentView = 'table';
        this.loading = false;
        
        this.init();
    }

    async init() {
        await this.loadProjects();
        await this.loadOwners();
        this.setupEventListeners();
    }

    async loadProjects() {
        if (this.loading) return;
        
        this.loading = true;
        this.showLoadingState();

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.pageSize,
                sort: this.sortField,
                order: this.sortOrder,
                ...this.filters
            });

            const response = await fetch(`/api/projects?${params}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch projects');
            }

            const data = await response.json();
            this.projects = data.data || [];
            this.totalPages = data.meta?.last_page || 1;
            
            this.renderProjects();
            this.updatePagination();
            this.updateCount();

        } catch (error) {
            console.error('Failed to load projects:', error);
            this.showError('Failed to load projects. Please try again.');
        } finally {
            this.loading = false;
        }
    }

    async loadOwners() {
        try {
            const response = await fetch('/api/app/users', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                const data = await response.json();
                const ownerSelect = document.getElementById('owner');
                ownerSelect.innerHTML = '<option value="">All Owners</option>';
                
                data.data.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    ownerSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load owners:', error);
        }
    }

    renderProjects() {
        if (this.projects.length === 0) {
            this.showEmptyState();
            return;
        }

        this.hideEmptyState();
        
        if (this.currentView === 'table') {
            this.renderTableView();
        } else {
            this.renderGridView();
        }
    }

    renderTableView() {
        const tbody = document.getElementById('projects-table-body');
        
        tbody.innerHTML = this.projects.map(project => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="checkbox" 
                           class="project-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                           value="${project.id}"
                           onchange="toggleProjectSelection('${project.id}')">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-project-diagram text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${project.name}</div>
                            <div class="text-sm text-gray-500">${project.description || 'No description'}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusColor(project.status)}">
                        ${project.status}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${project.owner?.name || 'No owner'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: ${project.progress || 0}%"></div>
                        </div>
                        <span class="text-sm text-gray-600">${project.progress || 0}%</span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${project.due_date ? this.formatDate(project.due_date) : 'No due date'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end space-x-2">
                        <button onclick="viewProject('${project.id}')" 
                                class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.edit')): ?>
                            <button onclick="editProject('${project.id}')" 
                                    class="text-gray-600 hover:text-gray-900">
                                <i class="fas fa-edit"></i>
                            </button>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.delete')): ?>
                            <button onclick="deleteProject('${project.id}')" 
                                    class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    renderGridView() {
        const grid = document.getElementById('projects-grid');
        
        grid.innerHTML = this.projects.map(project => `
            <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center">
                        <div class="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-project-diagram text-blue-600 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-semibold text-gray-900">${project.name}</h3>
                            <p class="text-sm text-gray-500">${project.owner?.name || 'No owner'}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${this.getStatusColor(project.status)}">
                        ${project.status}
                    </span>
                </div>
                
                <p class="text-sm text-gray-600 mb-4">${project.description || 'No description'}</p>
                
                <div class="mb-4">
                    <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
                        <span>Progress</span>
                        <span>${project.progress || 0}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${project.progress || 0}%"></div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                    <span>Due: ${project.due_date ? this.formatDate(project.due_date) : 'No due date'}</span>
                    <span>Created: ${this.formatDate(project.created_at)}</span>
                </div>
                
                <div class="flex items-center justify-end space-x-2">
                    <button onclick="viewProject('${project.id}')" 
                            class="text-blue-600 hover:text-blue-900 text-sm">
                        <i class="fas fa-eye mr-1"></i>
                        View
                    </button>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.edit')): ?>
                        <button onclick="editProject('${project.id}')" 
                                class="text-gray-600 hover:text-gray-900 text-sm">
                            <i class="fas fa-edit mr-1"></i>
                            Edit
                        </button>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('projects.delete')): ?>
                        <button onclick="deleteProject('${project.id}')" 
                                class="text-red-600 hover:text-red-900 text-sm">
                            <i class="fas fa-trash mr-1"></i>
                            Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        `).join('');
    }

    updatePagination() {
        const info = document.getElementById('pagination-info');
        const start = (this.currentPage - 1) * this.pageSize + 1;
        const end = Math.min(this.currentPage * this.pageSize, this.projects.length);
        const total = this.projects.length;
        
        info.textContent = `Showing ${start} to ${end} of ${total} results`;
        
        // Update pagination buttons
        document.getElementById('first-page-btn').disabled = this.currentPage === 1;
        document.getElementById('prev-page-btn').disabled = this.currentPage === 1;
        document.getElementById('next-page-btn').disabled = this.currentPage === this.totalPages;
        document.getElementById('last-page-btn').disabled = this.currentPage === this.totalPages;
        
        // Generate page numbers
        this.generatePageNumbers();
    }

    generatePageNumbers() {
        const container = document.getElementById('page-numbers');
        const pages = [];
        const maxVisible = 5;
        
        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(this.totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        for (let i = start; i <= end; i++) {
            pages.push(`
                <button onclick="goToPage(${i})" 
                        class="px-3 py-2 text-sm font-medium ${i === this.currentPage ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300'} border rounded-md hover:bg-gray-50">
                    ${i}
                </button>
            `);
        }
        
        container.innerHTML = pages.join('');
    }

    updateCount() {
        const count = document.getElementById('projects-count');
        count.textContent = `${this.projects.length} projects`;
    }

    showLoadingState() {
        // Loading states are already shown in HTML
    }

    showEmptyState() {
        document.getElementById('table-view').classList.add('hidden');
        document.getElementById('grid-view').classList.add('hidden');
        document.getElementById('empty-state').classList.remove('hidden');
    }

    hideEmptyState() {
        document.getElementById('empty-state').classList.add('hidden');
        if (this.currentView === 'table') {
            document.getElementById('table-view').classList.remove('hidden');
        } else {
            document.getElementById('grid-view').classList.remove('hidden');
        }
    }

    getStatusColor(status) {
        const colors = {
            'planning': 'bg-yellow-100 text-yellow-800',
            'active': 'bg-green-100 text-green-800',
            'on_hold': 'bg-red-100 text-red-800',
            'completed': 'bg-blue-100 text-blue-800',
            'cancelled': 'bg-gray-100 text-gray-800'
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    setupEventListeners() {
        // Auto-refresh every 5 minutes
        setInterval(() => {
            this.loadProjects();
        }, 300000);
    }
}

// Global variables
let projectsManager;
let currentPage = 1;
let totalPages = 1;

// Initialize projects manager when page loads
document.addEventListener('DOMContentLoaded', () => {
    projectsManager = new ProjectsManager();
});

// Global functions
function handleSearch(event) {
    if (event.key === 'Enter') {
        const search = event.target.value;
        projectsManager.filters.search = search;
        projectsManager.currentPage = 1;
        projectsManager.loadProjects();
    }
}

function handleFilterChange() {
    const status = document.getElementById('status').value;
    const owner = document.getElementById('owner').value;
    const dateRange = document.getElementById('date_range').value;
    
    projectsManager.filters = {
        status: status || undefined,
        owner_id: owner || undefined,
        date_range: dateRange || undefined
    };
    
    projectsManager.currentPage = 1;
    projectsManager.loadProjects();
}

function clearFilters() {
    document.getElementById('search').value = '';
    document.getElementById('status').value = '';
    document.getElementById('owner').value = '';
    document.getElementById('date_range').value = '';
    
    projectsManager.filters = {};
    projectsManager.currentPage = 1;
    projectsManager.loadProjects();
}

function refreshProjects() {
    projectsManager.loadProjects();
}

function toggleView(view) {
    projectsManager.currentView = view;
    
    // Update button states
    document.getElementById('view-table-btn').classList.toggle('text-blue-600', view === 'table');
    document.getElementById('view-table-btn').classList.toggle('text-gray-400', view !== 'table');
    document.getElementById('view-grid-btn').classList.toggle('text-blue-600', view === 'grid');
    document.getElementById('view-grid-btn').classList.toggle('text-gray-400', view !== 'grid');
    
    // Show/hide views
    document.getElementById('table-view').classList.toggle('hidden', view !== 'table');
    document.getElementById('grid-view').classList.toggle('hidden', view !== 'grid');
    
    // Re-render projects
    projectsManager.renderProjects();
}

function sortBy(field) {
    if (projectsManager.sortField === field) {
        projectsManager.sortOrder = projectsManager.sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
        projectsManager.sortField = field;
        projectsManager.sortOrder = 'asc';
    }
    
    projectsManager.loadProjects();
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        projectsManager.currentPage = page;
        projectsManager.loadProjects();
    }
}

function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.project-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    projectsManager.selectedProjects = selectAll.checked ? 
        Array.from(checkboxes).map(cb => cb.value) : [];
}

function toggleProjectSelection(projectId) {
    const checkbox = document.querySelector(`input[value="${projectId}"]`);
    if (checkbox.checked) {
        projectsManager.selectedProjects.push(projectId);
    } else {
        projectsManager.selectedProjects = projectsManager.selectedProjects.filter(id => id !== projectId);
    }
    
    // Update select all checkbox
    const allCheckboxes = document.querySelectorAll('.project-checkbox');
    const checkedCheckboxes = document.querySelectorAll('.project-checkbox:checked');
    document.getElementById('select-all').checked = allCheckboxes.length === checkedCheckboxes.length;
}

function viewProject(projectId) {
    if (projectId) {
        window.location.href = `/app/projects/${projectId}`;
    }
}

function editProject(projectId) {
    if (projectId) {
        window.location.href = `/app/projects/${projectId}/edit`;
    }
}

function deleteProject(projectId) {
    if (confirm('Are you sure you want to delete this project?')) {
        // Implement delete functionality
        console.log('Delete project:', projectId);
    }
}

function exportProjects() {
    // Implement export functionality
    console.log('Export projects');
}

function closeProjectActions() {
    document.getElementById('project-actions-modal').classList.add('hidden');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_legacy/projects/projects-new-legacy.blade.php ENDPATH**/ ?>