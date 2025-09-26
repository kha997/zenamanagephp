<?php $__env->startSection('title', 'App Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="appSPA()" class="min-h-screen bg-gray-50">
    <!-- SECURITY WARNING BANNER -->
    <?php if(!app()->environment('production')): ?>
    <div class="bg-red-600 text-white px-4 py-2 text-center text-sm font-semibold">
        🚨 AUTH DISABLED (DEV ONLY) - Dashboard routes moved to /_debug namespace for security
    </div>
    <?php endif; ?>
    
    <!-- Include App Header Component -->
    <?php echo $__env->make('components.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    <!-- App Navigation Menu -->
    <div class="bg-white shadow-sm border-b sticky top-20 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Navigation Menu -->
            <nav class="border-t border-gray-200 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <!-- Dashboard -->
                        <button @click="navigateTo('dashboard')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'dashboard' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </button>
                        
                        <!-- Projects -->
                        <button @click="navigateTo('projects')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'projects' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-project-diagram"></i>
                            <span>Projects</span>
                        </button>
                        
                        <!-- Tasks -->
                        <button @click="navigateTo('tasks')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'tasks' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-tasks"></i>
                            <span>Tasks</span>
                        </button>
                        
                        <!-- Calendar -->
                        <button @click="navigateTo('calendar')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'calendar' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Calendar</span>
                        </button>
                        
                        <!-- Documents -->
                        <button @click="navigateTo('documents')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'documents' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-file-alt"></i>
                            <span>Documents</span>
                        </button>
                        
                        <!-- Templates -->
                        <button @click="navigateTo('templates')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'templates' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-layer-group"></i>
                            <span>Templates</span>
                        </button>
                        
                        <!-- Team -->
                        <button @click="navigateTo('team')" class="app-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'team' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-users"></i>
                            <span>Team</span>
                        </button>
                    </div>
                    
                    <!-- Refresh Button -->
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="refreshing ? 'animate-spin' : ''"></i>
                        <span x-show="!refreshing">Refresh</span>
                        <span x-show="refreshing">Refreshing...</span>
                    </button>
                </div>
            </nav>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900" x-text="getPageTitle()"></h1>
            <p class="mt-2 text-gray-600" x-text="getPageDescription()"></p>
        </div>
        
        <!-- Dashboard View -->
        <div x-show="currentView === 'dashboard'" x-transition>
            <?php echo $__env->make('app.dashboard-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        <!-- Projects View -->
        <div x-show="currentView === 'projects'" x-transition>
            <?php echo $__env->make('app.projects-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        <!-- Tasks View -->
        <div x-show="currentView === 'tasks'" x-transition>
            <?php echo $__env->make('app.tasks-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        <!-- Calendar View -->
        <div x-show="currentView === 'calendar'" x-transition>
            <?php echo $__env->make('app.calendar-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        <!-- Documents View -->
        <div x-show="currentView === 'documents'" x-transition>
            <?php echo $__env->make('app.documents-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        <!-- Templates View -->
        <div x-show="currentView === 'templates'" x-transition>
            <?php echo $__env->make('app.templates-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        <!-- Team View -->
        <div x-show="currentView === 'team'" x-transition>
            <?php echo $__env->make('app.team-content', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </main>
</div>

<style>
    /* App Navigation Button Styling */
    .app-nav-button {
        transition: all 0.2s ease-in-out;
        position: relative;
    }

    .app-nav-button.active {
        background: #3b82f6;
        color: white;
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
    }

    .app-nav-button:hover:not(.active) {
        background-color: #f0f9ff; /* Blue-50 */
        color: #1e40af; /* Blue-800 */
    }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('appSPA', () => ({
            // Authentication state
            isAuthenticated: false,
            authToken: null,
            currentUser: null,
            authError: null,

            // Dashboard Data (merged from dashboardData)
            alerts: [],
            kpis: {
                totalProjects: 0,
                activeProjects: 0,
                onTimeRate: 0,
                overdueProjects: 0,
                budgetUsage: 0,
                overBudgetProjects: 0,
                healthSnapshot: 0,
                atRiskProjects: 0,
                activeTasks: 0,
                completedToday: 0,
                teamMembers: 0,
                projects: 0
            },
            nowPanel: [],
            nowPanelActions: [],
            workQueue: { my_work: { tasks: [], total: 0 }, team_work: { tasks: [], total: 0 } },
            activity: [],
            shortcuts: [],
            focusMode: { is_active: false, current_task: null, focus_time_today: 0 },
            activeTab: 'my',
            activityFilter: 'all',
            loading: true,
            error: null,
            darkMode: false,
            chartPeriod: '7d',
            charts: {},
            chartsInitialized: false,
            customizeMode: false,
            
            // Search & Filter State
            globalSearchQuery: '',
            searchQuery: '',
            showSearchSuggestions: false,
            searchSuggestions: [],
            showAdvancedFilters: false,
            showFilters: false,
            filters: {
                dateFrom: '',
                dateTo: '',
                priority: '',
                status: '',
                assignee: ''
            },
            filterOptions: {
                pms: [],
                clients: [],
                tags: [],
                locations: []
            },
            quickFilterTags: [
                { id: 'today', label: 'Today', icon: 'fas fa-calendar-day', active: false },
                { id: 'high_priority', label: 'High Priority', icon: 'fas fa-exclamation', active: false },
                { id: 'my_tasks', label: 'My Tasks', icon: 'fas fa-user', active: false },
                { id: 'overdue', label: 'Overdue', icon: 'fas fa-clock', active: false },
                { id: 'completed', label: 'Completed', icon: 'fas fa-check', active: false }
            ],
            activeFiltersCount: 0,
            activeFilters: [],
            selectedProjects: [],
            tableColumns: [],
            visibleColumns: [],
            sortedProjects: [],
            kanbanSettings: {
                showProgress: true,
                showDueDates: true,
                showHealth: true
            },
            kanbanColumns: [],
            currentPage: 1,
            totalItems: 0,
            insightsTimeRange: '7d',
            chartTypes: {
                status: 'pie',
                progress: 'line',
                budget: 'bar',
                team: 'radar'
            },
            insights: {
                avgCompletionTime: 0,
                completionTimeChange: 0,
                budgetUtilization: 0,
                budgetChange: 0,
                teamEfficiency: 0,
                efficiencyChange: 0,
                qualityScore: 0,
                qualityChange: 0,
                metrics: []
            },
            showActivityDetails: false,
            filteredActivities: [],
            hasMoreActivities: false,
            projectShortcuts: [],
            teamShortcuts: [],
            taskShortcuts: [],
            systemShortcuts: [],
            sideDrawerOpen: false,
            selectedProject: {
                id: null,
                name: '',
                code: '',
                description: '',
                status: '',
                progress: 0,
                pm_name: '',
                client_name: '',
                members_count: 0,
                budget_total: 0,
                due_date: null,
                created_at: null,
                tags: [],
                health: 'good',
                priority: 'medium'
            },
            
            // User and UI State
            userName: 'John Doe',
            // mobileMenuOpen removed - no longer needed

            init() {
                this.loadInitialView();
                this.checkAuthentication().then(isAuthenticated => {
                    if (isAuthenticated) {
                        this.loadDashboardData();
                    } else {
                        console.log('⚠️ User not authenticated, using mock data');
                        this.loadDashboardData(); // This will fall back to mock data
                    }
                });
                
                window.addEventListener('popstate', (event) => {
                    if (event.state && event.state.view) {
                        this.currentView = event.state.view;
                    } else {
                        this.loadInitialView();
                    }
                });
            },

            loadInitialView() {
                const path = window.location.pathname;
                const view = path.split('/').pop();
                if (['projects', 'tasks', 'calendar', 'documents', 'templates', 'team'].includes(view)) {
                    this.currentView = view;
                } else {
                    this.currentView = 'dashboard';
                }
                window.history.replaceState({ view: this.currentView }, '', `/app/${this.currentView}`);
            },

            navigateTo(view) {
                this.currentView = view;
                window.history.pushState({ view: view }, '', `/app/${view}`);
            },

            refreshData() {
                this.refreshing = true;
                // Simulate data fetching
                setTimeout(() => {
                    console.log('App data refreshed!');
                    this.refreshing = false;
                    // In a real application, you would re-fetch data for the currentView
                }, 1500);
            },
            
            getPageTitle() {
                const titles = {
                    'dashboard': 'My Dashboard',
                    'projects': 'My Projects',
                    'tasks': 'My Tasks',
                    'calendar': 'My Calendar',
                    'documents': 'My Documents',
                    'templates': 'My Templates',
                    'team': 'My Team'
                };
                return titles[this.currentView] || 'My Dashboard';
            },
            
            getPageDescription() {
                const descriptions = {
                    'dashboard': 'Overview of your projects and tasks',
                    'projects': 'Manage your project portfolio',
                    'tasks': 'Manage your daily tasks and assignments',
                    'calendar': 'Schedule and track your project events',
                    'documents': 'Access and manage your documents',
                    'templates': 'Use and create project templates',
                    'team': 'Collaborate with your team members'
                };
                return descriptions[this.currentView] || 'Overview of your projects and tasks';
            },

            // Utility functions
            getProjectHealth(project) {
                if (!project) return 'healthy';
                // Mock health calculation
                return 'healthy';
            },
            
            formatCurrency(amount) {
                if (!amount) return '0 ₫';
                return new Intl.NumberFormat('vi-VN', {
                    style: 'currency',
                    currency: 'VND'
                }).format(amount);
            },
            
            isOverdue(date) {
                if (!date) return false;
                return new Date(date) < new Date();
            },
            
            formatDate(date) {
                if (!date) return 'N/A';
                return new Date(date).toLocaleDateString('vi-VN');
            },
            
            getProjectActivity(project) {
                if (!project) return [];
                return [];
            },
            
            // Missing functions
            getColumnCount(column) {
                // Mock function for kanban column count
                return Math.floor(Math.random() * 10) + 1;
            },
            
            getProjectsByStatus(column) {
                // Mock function for projects by status
                return [
                    {
                        id: 1,
                        name: 'Sample Project',
                        status: column.status,
                        progress: 75,
                        pm_name: 'John Doe',
                        client_name: 'ABC Corp',
                        due_date: '2024-02-15'
                    }
                ];
            },
            
            getFileIcon(mimeType) {
                // Mock function for file icon
                const iconMap = {
                    'application/pdf': 'fas fa-file-pdf',
                    'image/jpeg': 'fas fa-file-image',
                    'image/png': 'fas fa-file-image',
                    'text/plain': 'fas fa-file-alt',
                    'application/msword': 'fas fa-file-word',
                    'application/vnd.ms-excel': 'fas fa-file-excel'
                };
                return iconMap[mimeType] || 'fas fa-file';
            },
            
            formatFileSize(size) {
                // Mock function for file size formatting
                if (!size) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB'];
                let unitIndex = 0;
                let fileSize = parseFloat(size);
                
                while (fileSize >= 1024 && unitIndex < units.length - 1) {
                    fileSize /= 1024;
                    unitIndex++;
                }
                
                return `${fileSize.toFixed(1)} ${units[unitIndex]}`;
            },
            
            // toggleMobileMenu removed - no longer needed
            // closeMobileMenu removed - no longer needed
            
            // Search functions
            clearSearch() {
                this.globalSearchQuery = '';
                this.showSearchSuggestions = false;
            },
            
            selectSuggestion(suggestion) {
                this.globalSearchQuery = suggestion.title;
                this.showSearchSuggestions = false;
                // Implement search logic here
            },
            
            // Filter functions
            toggleAdvancedFilters() {
                this.showAdvancedFilters = !this.showAdvancedFilters;
            },
            
            resetAllFilters() {
                this.filters = {
                    dateFrom: '',
                    dateTo: '',
                    priority: '',
                    status: '',
                    assignee: ''
                };
                this.activeFilters = [];
                this.activeFiltersCount = 0;
            },
            
            toggleQuickFilter(tag) {
                tag.active = !tag.active;
                this.updateActiveFilters();
            },
            
            clearAllFilters() {
                this.quickFilterTags.forEach(tag => tag.active = false);
                this.activeFilters = [];
                this.activeFiltersCount = 0;
            },
            
            updateActiveFilters() {
                this.activeFilters = this.quickFilterTags.filter(tag => tag.active);
                this.activeFiltersCount = this.activeFilters.length;
            },
            
            // Dashboard functions
            toggleCustomizeMode() {
                this.customizeMode = !this.customizeMode;
            },
            
            resetLayout() {
                // Implement layout reset logic here
                console.log('Layout reset');
            },
            
            exportToPDF() {
                // Implement PDF export logic here
                console.log('Export to PDF');
            },
            
            exportToExcel() {
                // Implement Excel export logic here
                console.log('Export to Excel');
            },

            // Dashboard data loading
            async loadDashboardData() {
                if (this.loading === false) {
                    console.log('⏳ Dashboard data already loaded, skipping...');
                    return;
                }
                
                try {
                    this.loading = true;
                    this.error = null;
                    console.log('📊 Loading dashboard data from API...');
                    
                    // Get CSRF token first
                    const csrfResponse = await fetch('/api/csrf-token');
                    const csrfData = await csrfResponse.json();
                    
                    if (!csrfData.success) {
                        throw new Error('Failed to get CSRF token');
                    }
                    
                    // Load dashboard data from API
                    const response = await fetch('/api/dashboard/data', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfData.csrf_token,
                            'Accept': 'application/json',
                            ...(this.authToken && { 'Authorization': `Bearer ${this.authToken}` })
                        },
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load dashboard data');
                    }
                    
                    // Update KPIs with real data
                    this.kpis = {
                        totalProjects: data.data.kpis.totalProjects || 0,
                        activeProjects: data.data.kpis.activeProjects || 0,
                        onTimeRate: data.data.kpis.onTimeRate || 0,
                        overdueProjects: data.data.kpis.overdueProjects || 0,
                        budgetUsage: data.data.kpis.budgetUsage || 0,
                        overBudgetProjects: data.data.kpis.overBudgetProjects || 0,
                        healthSnapshot: data.data.kpis.healthSnapshot || 0,
                        atRiskProjects: data.data.kpis.atRiskProjects || 0,
                        activeTasks: data.data.kpis.activeTasks || 0,
                        completedToday: data.data.kpis.completedToday || 0,
                        teamMembers: data.data.kpis.teamMembers || 0,
                        projects: data.data.kpis.projects || 0
                    };
                    
                    // Update alerts with real data
                    this.alerts = data.data.alerts || [];
                    
                    // Update activities with real data
                    this.activities = data.data.activities || [];
                    this.filteredActivities = [...this.activities];
                    
                    // Initialize selectedProject with default values
                    this.selectedProject = {
                        id: null,
                        name: 'No Project Selected',
                        description: '',
                        status: 'planning',
                        progress: 0,
                        budget_total: 0,
                        actual_cost: 0,
                        start_date: null,
                        end_date: null,
                        due_date: null,
                        created_at: null,
                        updated_at: null,
                        team_members: [],
                        tasks: [],
                        documents: []
                    };
                    
                    console.log('✅ Dashboard data loaded successfully:', {
                        kpis: this.kpis,
                        alerts: this.alerts.length,
                        activities: this.activities.length
                    });
                    
                } catch (error) {
                    console.error('❌ Error loading dashboard data:', error);
                    this.error = error.message || 'Failed to load dashboard data';
                    
                    // Fallback to mock data if API fails
                    console.log('🔄 Falling back to mock data...');
                    this.kpis = {
                        totalProjects: 12,
                        activeProjects: 8,
                        onTimeRate: 85,
                        overdueProjects: 2,
                        budgetUsage: 75,
                        overBudgetProjects: 1,
                        healthSnapshot: 90,
                        atRiskProjects: 1,
                        activeTasks: 25,
                        completedToday: 5,
                        teamMembers: 8,
                        projects: 12
                    };
                    
                    this.alerts = [
                        {
                            id: 'overdue_tasks',
                            type: 'warning',
                            title: 'Overdue Tasks',
                            message: '3 tasks are overdue',
                            action_url: '/app/tasks?filter=overdue'
                        },
                        {
                            id: 'urgent_projects',
                            type: 'error',
                            title: 'Urgent Projects',
                            message: '2 projects due within 2 days',
                            action_url: '/app/projects?filter=urgent'
                        }
                    ];
                    
                    this.activities = [
                        {
                            id: 1,
                            type: 'task',
                            description: 'Task "Design Review" completed',
                            user: 'John Doe',
                            created_at: new Date().toISOString(),
                            metadata: {}
                        },
                        {
                            id: 2,
                            type: 'project',
                            description: 'New project "Mobile App" created',
                            user: 'Sarah Smith',
                            created_at: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
                            metadata: {}
                        }
                    ];
                    
                    this.filteredActivities = [...this.activities];
                    
                    this.selectedProject = {
                        id: null,
                        name: 'No Project Selected',
                        description: '',
                        status: 'planning',
                        progress: 0,
                        budget_total: 0,
                        actual_cost: 0,
                        start_date: null,
                        end_date: null,
                        due_date: null,
                        created_at: null,
                        updated_at: null,
                        team_members: [],
                        tasks: [],
                        documents: []
                    };
                    
                } finally {
                    this.loading = false;
                }
            },

            // Refresh dashboard data
            async refreshDashboardData() {
                console.log('🔄 Refreshing dashboard data...');
                this.loading = true;
                await this.loadDashboardData();
            },

            // Authentication methods
            async login(email, password, remember = false) {
                try {
                    this.authError = null;
                    
                    const response = await fetch('/api/auth/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            email: email,
                            password: password,
                            remember: remember
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.isAuthenticated = true;
                        this.authToken = data.token;
                        this.currentUser = data.user;
                        
                        // Store token in localStorage
                        localStorage.setItem('auth_token', data.token);
                        localStorage.setItem('user_data', JSON.stringify(data.user));
                        
                        console.log('✅ Login successful:', data.user.name);
                        
                        // Load dashboard data after successful login
                        await this.loadDashboardData();
                        
                        return { success: true };
                    } else {
                        this.authError = data.error || 'Login failed';
                        return { success: false, error: this.authError };
                    }
                    
                } catch (error) {
                    console.error('❌ Login error:', error);
                    this.authError = 'Login failed: ' + error.message;
                    return { success: false, error: this.authError };
                }
            },
            
            async logout() {
                try {
                    if (this.authToken) {
                        await fetch('/api/auth/logout', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${this.authToken}`,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });
                    }
                    
                    // Clear authentication state
                    this.isAuthenticated = false;
                    this.authToken = null;
                    this.currentUser = null;
                    this.authError = null;
                    
                    // Clear stored data
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_data');
                    
                    console.log('✅ Logout successful');
                    
                } catch (error) {
                    console.error('❌ Logout error:', error);
                }
            },
            
            async checkAuthentication() {
                try {
                    const token = localStorage.getItem('auth_token');
                    const userData = localStorage.getItem('user_data');
                    
                    if (!token || !userData) {
                        return false;
                    }
                    
                    // Validate token
                    const response = await fetch('/api/auth/validate', {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Accept': 'application/json'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.isAuthenticated = true;
                        this.authToken = token;
                        this.currentUser = JSON.parse(userData);
                        
                        console.log('✅ Authentication validated:', this.currentUser.name);
                        return true;
                    } else {
                        // Token is invalid, clear stored data
                        localStorage.removeItem('auth_token');
                        localStorage.removeItem('user_data');
                        return false;
                    }
                    
                } catch (error) {
                    console.error('❌ Authentication check error:', error);
                    return false;
                }
            },

            // Theme management
            initTheme() {
                // Check for saved theme preference or default to light mode
                const savedTheme = localStorage.getItem('theme');
                this.darkMode = savedTheme === 'dark';
                this.applyTheme();
            },

            toggleTheme() {
                this.darkMode = !this.darkMode;
                this.applyTheme();
                localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            },

            applyTheme() {
                if (this.darkMode) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            },

            // Chart management
            initCharts() {
                console.log('📊 initCharts called');
                
                // Check if Chart.js is available
                if (typeof Chart === 'undefined') {
                    console.log('Chart.js available: false');
                    console.log('⏳ Chart.js not available yet, retrying in 500ms...');
                    setTimeout(() => this.initCharts(), 500);
                    return;
                }
                
                console.log('Chart.js available: true');
                this.chartsInitialized = true;
                
                // Initialize charts here
                // This would contain the actual chart initialization logic
            }
        }));
        
        // Projects Page Data Function
        Alpine.data('projectsPage', () => ({
            loading: false,
            error: null,
            projects: [],
            searchQuery: '',
            showFilters: false,
            filters: {
                status: '',
                pm: '',
                client: '',
                dateRange: '',
                health: '',
                budget: '',
                tags: '',
                location: ''
            },
            filterOptions: {
                pms: [],
                clients: [],
                tags: [],
                locations: []
            },
            savedViews: [],
            activeFilters: [],
            selectedProjects: [],
            tableColumns: [
                { key: 'name', label: 'Project Name', visible: true },
                { key: 'status', label: 'Status', visible: true },
                { key: 'progress', label: 'Progress', visible: true },
                { key: 'pm_name', label: 'PM', visible: true },
                { key: 'client_name', label: 'Client', visible: true },
                { key: 'due_date', label: 'Due Date', visible: true }
            ],
            visibleColumns: ['name', 'status', 'progress', 'pm_name', 'client_name', 'due_date'],
            sortedProjects: [],
            kanbanSettings: {
                showProgress: true,
                showDueDates: true,
                showHealth: true
            },
            kanbanColumns: [
                { id: 'planning', title: 'Planning', projects: [] },
                { id: 'active', title: 'Active', projects: [] },
                { id: 'review', title: 'Review', projects: [] },
                { id: 'completed', title: 'Completed', projects: [] }
            ],
            currentPage: 1,
            totalItems: 0,
            itemsPerPage: 20,
            
            init() {
                this.loadProjects();
            },
            
            async loadProjects() {
                this.loading = true;
                this.error = null;
                try {
                    // Mock data for now
                    this.projects = [
                        {
                            id: 1,
                            name: 'Website Redesign',
                            status: 'active',
                            progress: 75,
                            pm_name: 'John Doe',
                            client_name: 'ABC Corp',
                            due_date: '2024-02-15'
                        }
                    ];
                    this.totalItems = this.projects.length;
                    this.sortedProjects = [...this.projects];
                } catch (error) {
                    this.error = 'Failed to load projects';
                    console.error('Error loading projects:', error);
                } finally {
                    this.loading = false;
                }
            },
            
            toggleFilters() {
                this.showFilters = !this.showFilters;
            },
            
            resetFilters() {
                this.filters = {
                    status: '',
                    pm: '',
                    client: '',
                    dateRange: '',
                    health: '',
                    budget: '',
                    tags: '',
                    location: ''
                };
                this.activeFilters = [];
            },
            
            applyFilters() {
                // Filter logic here
                this.activeFilters = Object.entries(this.filters)
                    .filter(([key, value]) => value !== '')
                    .map(([key, value]) => ({ key, value }));
            },
            
            selectProject(project) {
                const index = this.selectedProjects.findIndex(p => p.id === project.id);
                if (index > -1) {
                    this.selectedProjects.splice(index, 1);
                } else {
                    this.selectedProjects.push(project);
                }
            },
            
            selectAllProjects() {
                if (this.selectedProjects.length === this.projects.length) {
                    this.selectedProjects = [];
                } else {
                    this.selectedProjects = [...this.projects];
                }
            },
            
            get totalPages() {
                return Math.ceil(this.totalItems / this.itemsPerPage);
            },
            
            get paginatedProjects() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return this.sortedProjects.slice(start, end);
            },
            
            get filteredProjects() {
                return this.projects.filter(project => {
                    if (this.searchQuery) {
                        const query = this.searchQuery.toLowerCase();
                        return project.name.toLowerCase().includes(query) ||
                               project.client_name.toLowerCase().includes(query);
                    }
                    return true;
                });
            }
        }));
        
        // Documents Page Data Function
        Alpine.data('documentsPage', () => ({
            loading: false,
            error: null,
            documents: [],
            viewMode: 'grid',
            searchQuery: '',
            showFilters: false,
            filters: {
                fileType: '',
                status: '',
                project: '',
                dateRange: ''
            },
            activeFiltersCount: 0,
            activeFilters: [],
            filteredDocuments: [],
            
            init() {
                this.loadDocuments();
            },
            
            async loadDocuments() {
                this.loading = true;
                this.error = null;
                try {
                    // Mock data for now
                    this.documents = [
                        {
                            id: 1,
                            name: 'Project Proposal.pdf',
                            type: 'pdf',
                            size: '2.5 MB',
                            project: 'Website Redesign',
                            uploaded: '2024-01-15'
                        }
                    ];
                    this.filteredDocuments = [...this.documents];
                } catch (error) {
                    this.error = 'Failed to load documents';
                    console.error('Error loading documents:', error);
                } finally {
                    this.loading = false;
                }
            },
            
            toggleViewMode() {
                this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
            },
            
            toggleFilters() {
                this.showFilters = !this.showFilters;
            },
            
            resetFilters() {
                this.filters = {
                    fileType: '',
                    status: '',
                    project: '',
                    dateRange: ''
                };
                this.activeFilters = [];
                this.activeFiltersCount = 0;
            },
            
            applyFilters() {
                this.activeFilters = Object.entries(this.filters)
                    .filter(([key, value]) => value !== '')
                    .map(([key, value]) => ({ key, value }));
                this.activeFiltersCount = this.activeFilters.length;
            }
        }));
        
        // Header Component Data Function
        Alpine.data('headerComponent', () => ({
            // Header specific state
            isAdminPage: false,
            notifications: [],
            user: {
                name: 'John Doe',
                role: 'Project Manager',
                avatar: 'JD'
            },
            nowPanel: [],
            nowPanelActions: [],
            workQueue: { my_work: { tasks: [], total: 0 }, team_work: { tasks: [], total: 0 } },
            activity: [],
            shortcuts: [],
            focusMode: { is_active: false, current_task: null, focus_time_today: 0 },
            activeTab: 'my',
            activityFilter: 'all',
            loading: true,
            error: null,
            darkMode: false,
            chartPeriod: '7d',
            charts: {},
            chartsInitialized: false,
            customizeMode: false,
            
            // Search & Filter State
            globalSearchQuery: '',
            searchQuery: '',
            showSearchSuggestions: false,
            searchSuggestions: [],
            showAdvancedFilters: false,
            showFilters: false,
            filters: {
                dateFrom: '',
                dateTo: '',
                priority: '',
                status: '',
                assignee: ''
            },
            filterOptions: {
                pms: [],
                clients: [],
                tags: [],
                locations: []
            },
            quickFilterTags: [
                { id: 'today', label: 'Today', icon: 'fas fa-calendar-day', active: false },
                { id: 'high_priority', label: 'High Priority', icon: 'fas fa-exclamation', active: false },
                { id: 'my_tasks', label: 'My Tasks', icon: 'fas fa-user', active: false },
                { id: 'overdue', label: 'Overdue', icon: 'fas fa-clock', active: false },
                { id: 'completed', label: 'Completed', icon: 'fas fa-check', active: false }
            ],
            activeFiltersCount: 0,
            activeFilters: [],
            selectedProjects: [],
            tableColumns: [],
            visibleColumns: [],
            sortedProjects: [],
            kanbanSettings: {
                showProgress: true,
                showDueDates: true,
                showHealth: true
            },
            kanbanColumns: [],
            currentPage: 1,
            totalItems: 0,
            insightsTimeRange: '7d',
            chartTypes: {
                status: 'pie',
                progress: 'line',
                budget: 'bar',
                team: 'radar'
            },
            insights: {
                avgCompletionTime: 0,
                completionTimeChange: 0,
                budgetUtilization: 0,
                budgetChange: 0,
                teamEfficiency: 0,
                efficiencyChange: 0,
                qualityScore: 0,
                qualityChange: 0,
                metrics: []
            },
            showActivityDetails: false,
            filteredActivities: [],
            hasMoreActivities: false,
            projectShortcuts: [],
            teamShortcuts: [],
            taskShortcuts: [],
            systemShortcuts: [],
            sideDrawerOpen: false,
            selectedProject: null,
            
            // User and UI State
            userName: 'John Doe',
            // mobileMenuOpen removed - no longer needed
            
            // Utility functions
            getProjectHealth(project) {
                if (!project) return 'healthy';
                // Mock health calculation
                return 'healthy';
            },
            
            formatCurrency(amount) {
                if (!amount) return '0 ₫';
                return new Intl.NumberFormat('vi-VN', {
                    style: 'currency',
                    currency: 'VND'
                }).format(amount);
            },
            
            isOverdue(date) {
                if (!date) return false;
                return new Date(date) < new Date();
            },
            
            formatDate(date) {
                if (!date) return 'N/A';
                return new Date(date).toLocaleDateString('vi-VN');
            },
            
            getProjectActivity(project) {
                if (!project) return [];
                return [];
            },
            
            // Missing functions
            getColumnCount(column) {
                // Mock function for kanban column count
                return Math.floor(Math.random() * 10) + 1;
            },
            
            getProjectsByStatus(column) {
                // Mock function for projects by status
                return [
                    {
                        id: 1,
                        name: 'Sample Project',
                        status: column.status,
                        progress: 75,
                        pm_name: 'John Doe',
                        client_name: 'ABC Corp',
                        due_date: '2024-02-15'
                    }
                ];
            },
            
            getFileIcon(mimeType) {
                // Mock function for file icon
                const iconMap = {
                    'application/pdf': 'fas fa-file-pdf',
                    'image/jpeg': 'fas fa-file-image',
                    'image/png': 'fas fa-file-image',
                    'text/plain': 'fas fa-file-alt',
                    'application/msword': 'fas fa-file-word',
                    'application/vnd.ms-excel': 'fas fa-file-excel'
                };
                return iconMap[mimeType] || 'fas fa-file';
            },
            
            formatFileSize(size) {
                // Mock function for file size formatting
                if (!size) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB'];
                let unitIndex = 0;
                let fileSize = parseFloat(size);
                
                while (fileSize >= 1024 && unitIndex < units.length - 1) {
                    fileSize /= 1024;
                    unitIndex++;
                }
                
                return `${fileSize.toFixed(1)} ${units[unitIndex]}`;
            },
            
            // toggleMobileMenu removed - no longer needed
            // closeMobileMenu removed - no longer needed
            
            // Search functions
            clearSearch() {
                this.globalSearchQuery = '';
                this.showSearchSuggestions = false;
            },
            
            selectSuggestion(suggestion) {
                this.globalSearchQuery = suggestion.title;
                this.showSearchSuggestions = false;
                // Implement search logic here
            },
            
            // Filter functions
            toggleAdvancedFilters() {
                this.showAdvancedFilters = !this.showAdvancedFilters;
            },
            
            resetAllFilters() {
                this.filters = {
                    dateFrom: '',
                    dateTo: '',
                    priority: '',
                    status: '',
                    assignee: ''
                };
                this.activeFilters = [];
                this.activeFiltersCount = 0;
            },
            
            toggleQuickFilter(tag) {
                tag.active = !tag.active;
                this.updateActiveFilters();
            },
            
            clearAllFilters() {
                this.quickFilterTags.forEach(tag => tag.active = false);
                this.activeFilters = [];
                this.activeFiltersCount = 0;
            },
            
            updateActiveFilters() {
                this.activeFilters = this.quickFilterTags.filter(tag => tag.active);
                this.activeFiltersCount = this.activeFilters.length;
            },
            
            // Dashboard functions
            toggleCustomizeMode() {
                this.customizeMode = !this.customizeMode;
            },
            
            resetLayout() {
                // Implement layout reset logic here
                console.log('Layout reset');
            },
            
            exportToPDF() {
                // Implement PDF export logic here
                console.log('Export to PDF');
            },
            
            exportToExcel() {
                // Implement Excel export logic here
                console.log('Export to Excel');
            },
            
            init() {
                console.log('🚀 Dashboard init started');
                this.initTheme();
                this.loadDashboardData();
                
                // Wait for DOM to be ready and then init charts (only once)
                if (!this.chartsInitialized) {
                    setTimeout(() => {
                        console.log('📊 Initializing charts...');
                        this.initCharts();
                    }, 100);
                }
            },
            
            // Theme management
            initTheme() {
                const savedTheme = localStorage.getItem('darkMode');
                this.darkMode = savedTheme === 'true';
                this.updateTheme();
            },
            
            toggleDarkMode() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('darkMode', this.darkMode);
                this.updateTheme();
            },
            
            updateTheme() {
                if (this.darkMode) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            },
            
            // Error handling
            retryLoad() {
                this.error = null;
                this.loading = true;
                this.loadDashboardData();
            },
            
            // Projects functions (needed for dashboard context)
            sortColumn: 'name',
            sortDirection: 'asc',
            
            sortBy(columnKey) {
                if (this.sortColumn === columnKey) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = columnKey;
                    this.sortDirection = 'asc';
                }
            },
            
            getBudgetStatus(project) {
                // Mock budget calculation
                const usedBudget = project.budget_total * 0.75; // Mock 75% usage
                if (usedBudget > project.budget_total) return 'overbudget';
                if (usedBudget < project.budget_total * 0.5) return 'underbudget';
                return 'onbudget';
            },
            
            getProjectPriority(project) {
                // Mock priority calculation
                if (project.status === 'on_hold') return 'high';
                if (project.status === 'planning') return 'medium';
                if (project.status === 'active') return 'low';
                return 'medium';
            },
            
            getSortValue(project, columnKey) {
                // Get sortable value for a project column
                switch (columnKey) {
                    case 'name':
                        return project.name || '';
                    case 'status':
                        return project.status || '';
                    case 'progress':
                        return project.progress || 0;
                    case 'budget_total':
                        return project.budget_total || 0;
                    case 'due_date':
                        return project.due_date ? new Date(project.due_date) : new Date(0);
                    case 'created_at':
                        return project.created_at ? new Date(project.created_at) : new Date(0);
                    default:
                        return project[columnKey] || '';
                }
            },
            
            updateColumnVisibility() {
                // Update visible columns based on tableColumns visibility settings
                this.visibleColumns = this.tableColumns.filter(column => column.visible);
            },
            
            toggleProjectSelection(projectId) {
                // Toggle project selection in the selectedProjects array
                const index = this.selectedProjects.indexOf(projectId);
                if (index > -1) {
                    this.selectedProjects.splice(index, 1);
                } else {
                    this.selectedProjects.push(projectId);
                }
            },
            
            toggleSelectAll() {
                // Toggle select all projects
                if (this.selectedProjects.length === this.sortedProjects.length) {
                    this.selectedProjects = [];
                } else {
                    this.selectedProjects = this.sortedProjects.map(project => project.id);
                }
            },
            
            viewProject(projectId) {
                // View project details
                console.log('Viewing project:', projectId);
                // Set selected project for details view
                this.selectedProject = this.sortedProjects.find(project => project.id === projectId) || null;
            },
            
            // Chart initialization
            initCharts() {
                if (this.chartsInitialized) {
                    console.log('⏳ Charts already initialized, skipping...');
                    return;
                }
                
                console.log('📊 initCharts called');
                
                // Check if Chart.js is available
                if (typeof Chart === 'undefined') {
                    console.log('Chart.js available: false');
                    console.log('⏳ Chart.js not available yet, retrying in 500ms...');
                    setTimeout(() => this.initCharts(), 500);
                    return;
                }
                
                console.log('Chart.js available: true');
                this.chartsInitialized = true;
                
                try {
                    this.createTaskCompletionChart();
                    console.log('✅ All charts initialized successfully');
                } catch (error) {
                    console.error('Error initializing charts:', error);
                }
            },
            
            createTaskCompletionChart() {
                const canvas = document.getElementById('taskCompletionChart');
                if (!canvas) {
                    console.log('❌ taskCompletionChart canvas not found');
                    return;
                }
                
                console.log('✅ taskCompletionChart canvas found');
                
                const ctx = canvas.getContext('2d');
                
                // Destroy existing chart if it exists
                if (this.charts.taskCompletion) {
                    this.charts.taskCompletion.destroy();
                }
                
                this.charts.taskCompletion = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'In Progress', 'Pending'],
                        datasets: [{
                            data: [65, 25, 10],
                            backgroundColor: [
                                '#10B981', // Green
                                '#F59E0B', // Yellow
                                '#EF4444'  // Red
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
                
                console.log('✅ Task Completion Chart created');
            }
        }));
        
        // Header Component Data Function
        Alpine.data('headerComponent', () => ({
            isAdminPage: false,
            notifications: [],
            user: {
                name: 'John Doe',
                email: 'john.doe@company.com',
                role: 'Project Manager',
                avatar: 'JD'
            },
            
            init() {
                console.log('Header component initialized');
                this.checkAdminPage();
                this.loadNotifications();
            },
            
            checkAdminPage() {
                this.isAdminPage = window.location.pathname.startsWith('/admin');
            },
            
            async loadNotifications() {
                // Mock notifications
                this.notifications = [
                    {
                        id: 1,
                        title: 'New Project Assigned',
                        message: 'You have been assigned to "Website Redesign" project',
                        time: '2 minutes ago',
                        type: 'info'
                    }
                ];
            },
            
            toggleNotifications() {
                // Toggle notifications dropdown
            },
            
            logout() {
                window.location.href = '/logout';
            }
        }));
        
        console.log('✅ All Alpine.js data functions loaded successfully');
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app-base', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/app-layout.blade.php ENDPATH**/ ?>