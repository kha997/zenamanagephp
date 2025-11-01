@extends('layouts.app')

@section('title', 'Projects')

@section('kpi-strip')
    {{-- KPI Strip section - can be implemented later --}}
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    
    /* Mobile improvements */
    @media (max-width: 768px) {
        /* Filter grid - single column on mobile */
        .filter-grid-mobile {
            grid-template-columns: 1fr !important;
        }
        
        /* Kanban horizontal scroll */
        .kanban-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Card grid - 1 column on mobile */
        .projects-grid-mobile {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
        
        /* Better touch targets */
        button, a {
            min-height: 44px;
            min-width: 44px;
        }
    }
</style>
@endpush

@section('content')
@php
    use App\Presenters\ProjectPresenter;
    
    $projectItems = ProjectPresenter::formatForView($projects ?? []);
    $paginationMeta = ProjectPresenter::formatPaginationMeta($projects ?? []);
    $clientOptions = ProjectPresenter::formatClientOptions($clients ?? collect());
    
    $initialFilters = array_merge([
        'search' => '',
        'status' => '',
        'priority' => '',
        'client_id' => '',
        'sort_by' => 'name',
        'sort_direction' => 'asc',
    ], $filters ?? []);

    $projectsApiUrl = url('/api/projects');
@endphp

<div
    x-data="projectsPage({
        projects: @json($projectItems),
        filters: @json($initialFilters),
        pagination: @json($paginationMeta),
        clients: @json($clientOptions),
        viewMode: @json($viewMode ?? 'card'),
        apiEndpoint: @json($projectsApiUrl),
        csrfToken: @json(csrf_token())
    })"
    x-init="init()"
    class="space-y-6"
>
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm uppercase tracking-wide text-gray-400">Workspace</p>
            <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
            <p class="text-sm text-gray-500">Manage and track your projects</p>
        </div>
        <div class="flex flex-col gap-3 w-full lg:w-auto">
            <div class="flex flex-col sm:flex-row gap-3 w-full">
                <div class="flex-1 sm:flex-none sm:w-auto flex items-center rounded-xl bg-gray-100 p-1">
                    <button
                        type="button"
                        @click="setViewMode('table')"
                        :class="viewMode === 'table' ? 'bg-white text-gray-900 shadow' : 'text-gray-600'"
                        :aria-pressed="viewMode === 'table'"
                        aria-label="Switch to table view"
                        class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-table text-xs" aria-hidden="true"></i>
                        Table
                        </button>
                    <button
                        type="button"
                        @click="setViewMode('card')"
                        :class="viewMode === 'card' ? 'bg-white text-gray-900 shadow' : 'text-gray-600'"
                        :aria-pressed="viewMode === 'card'"
                        aria-label="Switch to card view"
                        class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-th-large text-xs" aria-hidden="true"></i>
                        Cards
                        </button>
                    <button
                        type="button"
                        @click="setViewMode('kanban')"
                        :class="viewMode === 'kanban' ? 'bg-white text-gray-900 shadow' : 'text-gray-600'"
                        :aria-pressed="viewMode === 'kanban'"
                        aria-label="Switch to kanban view"
                        class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-columns text-xs" aria-hidden="true"></i>
                        Kanban
                        </button>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <button
                            type="button"
                            :aria-expanded="showFilters"
                            aria-label="Toggle filters"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition"
                            @click="showFilters = !showFilters"
                        >
                            <i class="fas fa-sliders-h text-xs" aria-hidden="true"></i>
                            Filters
                        </button>
                        <a
                            href="/frontend/app/projects/create"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition"
                        >
                            <i class="fas fa-plus text-xs"></i>
                            New Project
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div
        x-show="showFilters"
        x-transition
        class="bg-white rounded-lg border border-gray-200 shadow-sm mb-6"
    >
        <div class="p-6 space-y-4">
            {{-- Search --}}
            <div>
                <div class="relative max-w-3xl mx-auto">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input
                        type="search"
                               x-model="filters.search"
                               @input.debounce.300ms="applyFilters()"
                               placeholder="Search projects..."
                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    />
                </div>
                </div>

            {{-- Filter Controls --}}
            <div class="filter-grid-mobile flex flex-wrap items-center justify-center gap-3">
                <select
                    x-model="filters.status"
                            @change="applyFilters()"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[150px] bg-white text-sm"
                >
                    <option value="">All Statuses</option>
                        <option value="planning">Planning</option>
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="archived">Archived</option>
                    </select>

                <select
                    x-model="filters.priority"
                            @change="applyFilters()"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[150px] bg-white text-sm"
                >
                    <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                    <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>

                <select
                    x-model="filters.client_id"
                            @change="applyFilters()"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[150px] bg-white text-sm"
                >
                        <option value="">All Clients</option>
                        @if(isset($clients) && $clients->count() > 0)
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        @endif
                    </select>

                <select
                    x-model="filters.sort_by"
                            @change="applyFilters()"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[150px] bg-white text-sm"
                >
                        <option value="name">Sort by Name</option>
                        <option value="status">Sort by Status</option>
                        <option value="priority">Sort by Priority</option>
                    <option value="due_date">Sort by Due Date</option>
                    <option value="progress_pct">Sort by Progress</option>
                    </select>

                <button
                    type="button"
                    @click="clearFilters()"
                    :class="hasActiveFilters() ? 'text-gray-700 hover:text-gray-900' : 'text-gray-400 cursor-not-allowed'"
                    :disabled="!hasActiveFilters()"
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 bg-white transition"
                >
                    <i class="fas fa-times"></i>
                    Clear filters
                    </button>
                </div>

            {{-- Active Filter Tags - Hidden để UI gọn hơn --}}
            {{--
            <div
                x-cloak
                x-show="hasActiveFilters()"
                x-transition
                class="flex flex-wrap items-center justify-center gap-2 pt-2"
            >
                <template x-for="filter in getActiveFilters()" :key="filter.key">
                    <span class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-full shadow-sm">
                        <span x-text="filter.label + ': ' + filter.value"></span>
                        <button type="button" class="text-gray-500 hover:text-gray-900" @click="removeFilter(filter.key)">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </span>
                </template>
                <button
                    type="button"
                    class="text-xs font-semibold text-blue-600 hover:text-blue-700 underline decoration-dotted"
                    @click="clearFilters()"
                >
                    Clear all
                </button>
            </div>
            --}}
        </div>
    </div>

    {{-- Main Content --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="p-6">
            {{-- Loading State --}}
            <div x-show="loading" x-cloak class="text-center py-16">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                <p class="text-gray-600 font-medium">Loading projects...</p>
                </div>

            {{-- Error State --}}
            <div x-show="error" x-cloak class="flex justify-center">
                <div class="max-w-xl w-full bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error loading projects</h3>
                    <p class="text-sm text-red-700 mb-4" x-text="error"></p>
                    <button
                        type="button"
                        @click="retryLoad()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg"
                    >
                        <i class="fas fa-redo"></i>
                        Retry
                    </button>
                </div>
            </div>

            {{-- Empty State --}}
            <div
                x-show="!loading && !error && filteredProjects.length === 0"
                x-cloak
                class="flex items-center justify-center min-h-[400px] py-12"
            >
                <div class="text-center max-w-md w-full">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-6"></i>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No projects found</h3>
                    <p class="text-sm text-gray-500 mb-6">Get started by creating your first project or adjusting the filters.</p>
                    <a
                        href="/frontend/app/projects/create"
                        class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-blue-600 rounded-lg shadow hover:bg-blue-700 transition"
                    >
                        <i class="fas fa-plus"></i>
                        Create Project
                    </a>
                </div>
            </div>
        </div>

        {{-- Table View --}}
            <div x-show="!loading && !error && filteredProjects.length > 0 && viewMode === 'table'" x-cloak>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Project</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Client</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Priority</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Due date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Progress</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 uppercase tracking-wide text-xs">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="project in paginatedProjects" :key="`table-${project.id}`">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-900" x-text="project.name || 'Untitled Project'"></p>
                                        <p class="text-xs text-gray-500" x-text="project.owner_name ? 'Owner: ' + project.owner_name : 'Unassigned'"></p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700" x-text="lookupClientName(project.client_id)"></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="formatStatus(project.status)"></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="formatPriority(project.priority)"></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="formatDate(project.due_date)"></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-900 font-semibold" x-text="`${normalizeProgress(project.progress_pct)}%`"></span>
                                            <div class="h-1.5 w-24 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full" :class="getProgressBarClass(project.progress_pct)" :style="{ width: `${normalizeProgress(project.progress_pct)}%` }"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                                            @click="viewProject(project)"
                                        >
                                                <i class="fas fa-eye"></i>
                                            View
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100"
                                            @click="editProject(project)"
                                        >
                                                <i class="fas fa-edit"></i>
                                            Edit
                                            </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Card View --}}
            <div x-show="!loading && !error && filteredProjects.length > 0 && viewMode === 'card'" x-cloak>
                <div class="projects-grid-mobile grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-for="project in paginatedProjects" :key="`card-${project.id}`">
                        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-1">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3 flex-1">
                                    <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-project-diagram text-xl"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-semibold text-gray-900 truncate" x-text="project.name || 'Untitled Project'"></h3>
                                        <p class="text-xs text-gray-500 truncate" x-text="lookupClientName(project.client_id)"></p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 line-clamp-2 mb-4" x-text="project.description || 'No description added yet.'"></p>
                            <div class="mt-5 flex items-center justify-between text-sm">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Tasks</p>
                                    <p class="font-semibold text-gray-900" x-text="getTaskSummary(project)"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 uppercase tracking-wide">Progress</p>
                                    <p class="text-2xl font-bold" :class="getProgressTextClass(project.progress_pct)" x-text="`${normalizeProgress(project.progress_pct)}%`"></p>
                                </div>
                            </div>
                            <div class="mt-2 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300"
                                     :class="getProgressBarClass(project.progress_pct)"
                                     :style="{ width: `${normalizeProgress(project.progress_pct)}%` }"></div>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3 text-sm text-gray-600">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-calendar text-orange-500"></i>
                                    <span x-text="formatDate(project.due_date)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-users text-purple-500"></i>
                                    <span x-text="formatMembers(project)"></span>
                                </div>
                            </div>
                            <div class="mt-6 flex gap-3">
                                <button
                                    type="button"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                                    @click="viewProject(project)"
                                >
                                    <i class="fas fa-eye text-xs"></i>
                                    View
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700"
                                    @click="editProject(project)"
                                >
                                    <i class="fas fa-edit text-xs"></i>
                                    Edit
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Kanban View --}}
            <div x-show="!loading && !error && filteredProjects.length > 0 && viewMode === 'kanban'" x-cloak>
                <div class="kanban-container grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="column in kanbanColumns" :key="`kanban-${column}`">
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800" x-text="formatStatus(column)"></p>
                                    <p class="text-xs text-gray-500" x-text="`${(groupedProjects[column]?.length || 0)} projects`"></p>
                                </div>
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-white text-sm font-semibold text-gray-600 border border-gray-200"
                                      x-text="groupedProjects[column]?.length || 0"></span>
                            </div>
                            <div class="space-y-3 min-h-[180px]">
                                <template x-for="project in groupedProjects[column] || []" :key="`kanban-card-${column}-${project.id}`">
                                    <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm hover:shadow-md transition">
                                        <p class="font-semibold text-gray-900 text-sm" x-text="project.name || 'Untitled Project'"></p>
                                        <p class="text-xs text-gray-500 mb-2" x-text="lookupClientName(project.client_id)"></p>
                                        <div class="flex items-center justify-between text-xs text-gray-600">
                                            <span class="font-semibold" x-text="`${normalizeProgress(project.progress_pct)}%`"></span>
                                            <span x-text="formatDate(project.due_date)"></span>
                                        </div>
                                    </div>
                                </template>
                                <p
                                    x-show="!(groupedProjects[column] && groupedProjects[column].length)"
                                    class="text-sm text-gray-500 italic text-center py-6"
                                >
                                    No projects in this column
                                </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

            {{-- Pagination --}}
            <div
                x-show="!loading && !error && filteredProjects.length > 0"
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-t border-gray-100 pt-4"
            >
                <div class="text-sm text-gray-600">
                    Showing
                    <span class="font-semibold text-gray-900" x-text="pagination.from"></span>
                    to
                    <span class="font-semibold text-gray-900" x-text="pagination.to"></span>
                    of
                    <span class="font-semibold text-gray-900" x-text="pagination.total"></span>
                    results
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white disabled:opacity-50"
                        :disabled="pagination.currentPage <= 1"
                        @click="goToPage('prev')"
                    >
                        <i class="fas fa-chevron-left text-xs"></i>
                        Prev
                    </button>
                    <span class="text-sm text-gray-600">
                        Page
                        <span class="font-semibold text-gray-900" x-text="pagination.currentPage"></span>
                        of
                        <span class="font-semibold text-gray-900" x-text="pagination.lastPage"></span>
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white disabled:opacity-50"
                        :disabled="pagination.currentPage >= pagination.lastPage"
                        @click="goToPage('next')"
                    >
                        Next
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function projectsPage(initialState = {}) {
    return {
        loading: false,
        error: null,
        viewMode: initialState.viewMode || 'card',
        showFilters: true,
        apiEndpoint: initialState.apiEndpoint || '/api/projects',
        csrfToken: initialState.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        pendingController: null,
        filters: Object.assign({
            search: '',
            status: '',
            priority: '',
            client_id: '',
            sort_by: 'name',
            sort_direction: 'asc'
        }, initialState.filters || {}),
        pagination: Object.assign({
            currentPage: initialState.pagination?.currentPage || 1,
            lastPage: initialState.pagination?.lastPage || 1,
            perPage: initialState.pagination?.perPage || 12,
            total: initialState.pagination?.total || 0,
            from: initialState.pagination?.from || 0,
            to: initialState.pagination?.to || 0,
        }, initialState.pagination || {}),
        rawProjects: Array.isArray(initialState.projects) ? initialState.projects : [],
        clientDirectory: initialState.clients || [],
        clientMap: {},
        
        // Computed properties for better performance
        get filteredProjects() {
            let filtered = Array.isArray(this.rawProjects) ? [...this.rawProjects] : [];
            
            // Apply filters
            if (this.filters.search) {
                const search = this.filters.search.toLowerCase();
                filtered = filtered.filter(p => 
                    (p.name || '').toLowerCase().includes(search) ||
                    (p.description || '').toLowerCase().includes(search) ||
                    (p.code || '').toLowerCase().includes(search)
                );
            }
            
            if (this.filters.status) {
                filtered = filtered.filter(p => (p.status || '').toLowerCase() === this.filters.status.toLowerCase());
            }
            
            if (this.filters.priority) {
                filtered = filtered.filter(p => (p.priority || '').toLowerCase() === this.filters.priority.toLowerCase());
            }
            
            if (this.filters.client_id) {
                filtered = filtered.filter(p => p.client_id == this.filters.client_id);
            }
            
            // Apply sorting
            if (this.filters.sort_by) {
                filtered.sort((a, b) => {
                    const aVal = a[this.filters.sort_by] || '';
                    const bVal = b[this.filters.sort_by] || '';
                    const direction = this.filters.sort_direction === 'desc' ? -1 : 1;
                    return (aVal > bVal ? 1 : -1) * direction;
                });
            }
            
            return filtered;
        },
        
        get paginatedProjects() {
            const start = (this.pagination.currentPage - 1) * this.pagination.perPage;
            const end = start + this.pagination.perPage;
            return this.filteredProjects.slice(start, end);
        },
        
        get groupedProjects() {
            const grouped = {};
            this.kanbanColumns.forEach(status => {
                grouped[status] = [];
            });
            this.filteredProjects.forEach(project => {
                const status = (project.status || '').toLowerCase();
                if (!grouped[status]) {
                    grouped[status] = [];
                }
                grouped[status].push(project);
            });
            return grouped;
        },
        kanbanColumns: ['planning', 'active', 'on_hold', 'completed', 'cancelled', 'archived'],
        statusLabels: {
            planning: 'Planning',
            active: 'Active',
            on_hold: 'On Hold',
            completed: 'Completed',
            cancelled: 'Cancelled',
            archived: 'Archived'
        },
        priorityLabels: {
            low: 'Low',
            medium: 'Medium',
            normal: 'Normal',
            high: 'High',
            urgent: 'Urgent'
        },

        init() {
            if (this.filters.sort && !this.filters.sort_by) {
                this.filters.sort_by = this.filters.sort;
            }
            if (!this.filters.sort_direction) {
                this.filters.sort_direction = 'asc';
            }

            this.clientDirectory.forEach(client => {
                if (client && client.id) {
                    this.clientMap[client.id] = client.name || 'Client';
                }
            });

            // Computed properties are now reactive - no need to manually set
            this.$watch('filteredProjects', () => {
                this.updatePaginationFromMeta({
                    current_page: this.pagination.currentPage,
                    last_page: this.pagination.lastPage,
                    per_page: this.pagination.perPage,
                    total: this.filteredProjects.length
                }, this.pagination.currentPage);
            });
        },

        setViewMode(mode) {
            this.viewMode = mode;
        },

        hasActiveFilters() {
            return Boolean(
                this.filters.search ||
                this.filters.status ||
                this.filters.priority ||
                this.filters.client_id
            );
        },

        getActiveFilters() {
            const active = [];
            if (this.filters.search) {
                active.push({ key: 'search', label: 'Search', value: this.filters.search });
            }
            if (this.filters.status) {
                active.push({ key: 'status', label: 'Status', value: this.formatStatus(this.filters.status) });
            }
            if (this.filters.priority) {
                active.push({ key: 'priority', label: 'Priority', value: this.formatPriority(this.filters.priority) });
            }
            if (this.filters.client_id) {
                active.push({ key: 'client_id', label: 'Client', value: this.lookupClientName(this.filters.client_id) });
            }
            return active;
        },

        removeFilter(key) {
            if (Object.prototype.hasOwnProperty.call(this.filters, key)) {
                this.filters[key] = '';
                this.applyFilters();
            }
        },

        clearFilters() {
            this.filters.search = '';
            this.filters.status = '';
            this.filters.priority = '';
            this.filters.client_id = '';
            this.filters.sort_by = 'name';
            this.filters.sort_direction = 'asc';
            this.applyFilters();
        },

        applyFilters() {
            this.pagination.currentPage = 1;
            this.fetchProjects({ page: 1 });
        },

        async fetchProjects({ page = 1, preservePage = false } = {}) {
            if (!this.apiEndpoint) {
                return;
            }

            if (!preservePage) {
                this.pagination.currentPage = page;
            }

            if (this.pendingController) {
                this.pendingController.abort();
            }

            const controller = new AbortController();
            this.pendingController = controller;
            this.loading = true;
            this.error = null;

            const params = this.buildQueryParams(page);
            const url = `${this.apiEndpoint}?${params.toString()}`;

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {})
                    },
                    credentials: 'include',
                    signal: controller.signal
                });

                if (!response.ok) {
                    const errorPayload = await response.json().catch(() => ({}));
                    throw new Error(errorPayload.message || 'Failed to load projects');
                }

                const payload = await response.json();
                const normalized = Array.isArray(payload.data)
                    ? payload.data.map(project => this.normalizeProject(project))
                    : [];

                this.rawProjects = normalized;
                this.filteredProjects = normalized;
                this.paginatedProjects = normalized;
                this.updatePaginationFromMeta(payload.meta || {}, page);
                this.groupedProjects = this.groupByStatus(this.paginatedProjects);
            } catch (fetchError) {
                if (fetchError.name === 'AbortError') {
                    return;
                }
                console.error('Projects fetch failed:', fetchError);
                this.error = fetchError.message || 'Unable to load projects. Please try again.';
            } finally {
                if (this.pendingController === controller) {
                    this.pendingController = null;
                }
                this.loading = false;
            }
        },

        buildQueryParams(page) {
            const params = new URLSearchParams();
            const perPage = this.pagination.perPage || 12;

            if (this.filters.search) params.set('search', this.filters.search);
            if (this.filters.status) params.set('status', this.filters.status);
            if (this.filters.priority) params.set('priority', this.filters.priority);
            if (this.filters.client_id) params.set('client_id', this.filters.client_id);
            if (this.filters.sort_by) params.set('sort_by', this.filters.sort_by);
            if (this.filters.sort_direction) params.set('sort_direction', this.filters.sort_direction);
            params.set('per_page', perPage);
            params.set('page', page || this.pagination.currentPage || 1);

            return params;
        },

        updatePaginationFromMeta(meta = {}, requestedPage = 1) {
            const perPage = Number(meta.per_page ?? this.pagination.perPage ?? 12);
            const total = typeof meta.total !== 'undefined' ? Number(meta.total) : this.pagination.total;
            const current = Number(meta.current_page ?? requestedPage ?? this.pagination.currentPage ?? 1);
            const lastPage = Number(meta.last_page ?? Math.max(1, Math.ceil((total || 0) / (perPage || 1))));

            this.pagination.perPage = perPage || 12;
            this.pagination.currentPage = current || 1;
            this.pagination.lastPage = lastPage;
            this.pagination.total = typeof total === 'number' ? total : this.pagination.total;

            if (!this.paginatedProjects.length) {
                this.pagination.from = 0;
                this.pagination.to = 0;
                return;
            }

            const start = (current - 1) * perPage + 1;
            this.pagination.from = start;
            this.pagination.to = start + this.paginatedProjects.length - 1;
        },

        async goToPage(direction) {
            let target = this.pagination.currentPage || 1;

            if (direction === 'next') {
                target = Math.min(this.pagination.currentPage + 1, this.pagination.lastPage);
            } else if (direction === 'prev') {
                target = Math.max(this.pagination.currentPage - 1, 1);
            } else if (typeof direction === 'number') {
                target = direction;
            }

            if (target === this.pagination.currentPage && this.paginatedProjects.length) {
                return;
            }

            await this.fetchProjects({ page: target, preservePage: true });
        },

        retryLoad() {
            const current = this.pagination.currentPage || 1;
            this.fetchProjects({ page: current, preservePage: true });
        },

        getTaskSummary(project) {
            const completed = Number(project.tasks_completed ?? 0);
            const total = Number(project.tasks_total ?? 0);
            if (!total) {
                return `${completed} tasks`;
            }
            return `${completed}/${total} tasks`;
        },

        normalizeProgress(progress) {
            const value = Number(progress ?? 0);
            return Math.max(0, Math.min(100, Math.round(value)));
        },

        formatMembers(project) {
            const members = Number(project.members_count ?? 0);
            if (members === 0) {
                return 'No members assigned';
            }
            return `${members} ${members === 1 ? 'member' : 'members'}`;
        },

        lookupClientName(clientId) {
            if (!clientId) {
                return 'No client';
            }
            return this.clientMap[clientId] || `Client #${clientId.toString().slice(-4)}`;
        },

        formatDate(value) {
            if (!value) {
                return 'No due date';
            }
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return date.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        },

        formatStatus(status) {
            if (!status) {
                return 'Unknown';
            }
            return this.statusLabels[status.toLowerCase()] || status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },

        formatPriority(priority) {
            if (!priority) {
                return 'None';
            }
            return this.priorityLabels[priority.toLowerCase()] || priority.replace(/\b\w/g, c => c.toUpperCase());
        },

        getStatusBadgeClass(status) {
            const map = {
                active: 'bg-green-100 text-green-800 border-green-200',
                planning: 'bg-blue-100 text-blue-800 border-blue-200',
                on_hold: 'bg-yellow-100 text-yellow-800 border-yellow-200',
                completed: 'bg-gray-100 text-gray-800 border-gray-200',
                cancelled: 'bg-red-100 text-red-800 border-red-200',
                archived: 'bg-purple-100 text-purple-800 border-purple-200'
            };
            return map[status] || 'bg-gray-100 text-gray-700 border-gray-200';
        },

        getPriorityAccentClass(priority) {
            const map = {
                low: 'bg-emerald-500',
                medium: 'bg-blue-500',
                normal: 'bg-blue-500',
                high: 'bg-orange-500',
                urgent: 'bg-red-500'
            };
            return map[priority] || 'bg-gray-300';
        },

        getProgressTextClass(progress) {
            const value = this.normalizeProgress(progress);
            if (value >= 75) return 'text-green-600';
            if (value >= 50) return 'text-blue-600';
            if (value >= 25) return 'text-yellow-500';
            return 'text-gray-500';
        },

        getProgressBarClass(progress) {
            const value = this.normalizeProgress(progress);
            if (value >= 75) return 'bg-gradient-to-r from-green-400 to-green-600';
            if (value >= 50) return 'bg-gradient-to-r from-blue-400 to-blue-600';
            if (value >= 25) return 'bg-gradient-to-r from-yellow-400 to-yellow-500';
            return 'bg-gradient-to-r from-gray-300 to-gray-400';
        },

        groupByStatus(data) {
            const grouped = {};
            this.kanbanColumns.forEach(status => {
                grouped[status] = [];
            });
            data.forEach(project => {
                const status = (project.status || '').toLowerCase();
                if (!grouped[status]) {
                    grouped[status] = [];
                }
                grouped[status].push(project);
            });
            return grouped;
        },

        normalizeProject(project) {
            if (!project) {
                return {};
            }

            return {
                id: project.id,
                code: project.code,
                name: project.name,
                description: project.description,
                status: project.status,
                priority: project.priority,
                progress_pct: project.progress_pct ?? project.progress ?? project.completion_percentage ?? 0,
                tasks_completed: project.tasks_completed ?? project.completed_tasks ?? project.tasks_done ?? 0,
                tasks_total: project.tasks_total ?? project.total_tasks ?? project.tasks_count ?? 0,
                members_count: project.members_count ?? project.team_members_count ?? (Array.isArray(project.members) ? project.members.length : 0),
                due_date: project.due_date,
                start_date: project.start_date,
                end_date: project.end_date,
                client_id: project.client_id,
                owner_name: project.owner_name ?? project.owner?.name ?? '',
                updated_at: project.updated_at,
                created_at: project.created_at,
            };
        },

        viewProject(project) {
            if (project?.id) {
                window.location.href = `/app/projects/${project.id}`;
            }
        },

        editProject(project) {
            if (project?.id) {
                window.location.href = `/app/projects/${project.id}/edit`;
            }
        }
    };
}
</script>
@endpush
