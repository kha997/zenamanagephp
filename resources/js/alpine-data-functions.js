// Alpine.js Data Functions for ZenaManage Dashboard
// This file contains all the missing Alpine.js data functions

document.addEventListener('alpine:init', () => {
    
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
    
    // Dashboard Data Function (Enhanced)
    Alpine.data('dashboardData', () => ({
        // State management
        alerts: [],
        kpis: {
            totalProjects: 0,
            activeProjects: 0,
            onTimeRate: 0,
            overdueProjects: 0,
            budgetUsage: 0,
            overBudgetProjects: 0,
            healthSnapshot: 0,
            atRiskProjects: 0
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
        
        // Data loading
        async loadDashboardData() {
            // Prevent multiple simultaneous loads
            if (this.loading && this.kpis && Object.keys(this.kpis).length > 0) {
                console.log('⏳ Dashboard data already loaded, skipping...');
                return;
            }
            
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading dashboard data...');
                
                // Fetch real data from API
                const response = await fetch('/api/v1/universal-frame/dashboard-data', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    this.kpis = data.data.kpis;
                    this.alerts = data.data.alerts || [];
                    this.nowPanelActions = data.data.nowPanelActions || [];
                    this.activity = data.data.activity || [];
                    
                    console.log('✅ Dashboard data loaded successfully');
                } else {
                    throw new Error(data.message || 'Failed to load dashboard data');
                }
                
                this.loading = false;
                
            } catch (error) {
                console.error('❌ Error loading dashboard data:', error);
                this.error = error.message;
                this.loading = false;
                
                // Fallback to empty data
                this.kpis = {
                    totalProjects: 0,
                    activeProjects: 0,
                    onTimeRate: 0,
                    overdueProjects: 0,
                    budgetUsage: 0,
                    overBudgetProjects: 0,
                    healthSnapshot: 0,
                    atRiskProjects: 0
                };
                this.alerts = [];
                this.nowPanelActions = [];
                this.activity = [];
            }
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
    
    // Team Page Data Function
    Alpine.data('teamPage', () => ({
        loading: false,
        error: null,
        teamMembers: [],
        searchQuery: '',
        showFilters: false,
        filters: {
            role: '',
            department: '',
            status: ''
        },
        activeFilters: [],
        
        init() {
            this.loadTeamMembers();
        },
        
        async loadTeamMembers() {
            this.loading = true;
            this.error = null;
            try {
                // Mock data for now
                this.teamMembers = [
                    {
                        id: 1,
                        name: 'John Doe',
                        email: 'john.doe@company.com',
                        role: 'Project Manager',
                        department: 'Engineering',
                        status: 'active',
                        avatar: 'JD'
                    }
                ];
                console.log('✅ Team members data loaded successfully');
            } catch (error) {
                this.error = 'Failed to load team members';
                console.error('Error loading team members:', error);
            } finally {
                this.loading = false;
            }
        },
        
        toggleFilters() {
            this.showFilters = !this.showFilters;
        },
        
        resetFilters() {
            this.filters = {
                role: '',
                department: '',
                status: ''
            };
            this.activeFilters = [];
        }
    }));
    
    // Tasks Page Data Function
    Alpine.data('tasksPage', () => ({
        loading: false,
        error: null,
        tasks: [],
        searchQuery: '',
        showFilters: false,
        filters: {
            status: '',
            priority: '',
            assignee: '',
            project: ''
        },
        activeFilters: [],
        selectedTasks: [],
        
        init() {
            this.loadTasks();
        },
        
        async loadTasks() {
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch('/api/tasks', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Failed to fetch tasks');
                }
                
                const data = await response.json();
                this.tasks = data.data || [];
            } catch (error) {
                this.error = 'Failed to load tasks';
                console.error('Error loading tasks:', error);
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
                priority: '',
                assignee: '',
                project: ''
            };
            this.activeFilters = [];
        }
    }));
    
    // Calendar Page Data Function
    Alpine.data('calendarPage', () => ({
        loading: false,
        error: null,
        events: [],
        currentDate: new Date(),
        view: 'month',
        
        init() {
            this.loadEvents();
        },
        
        async loadEvents() {
            this.loading = true;
            this.error = null;
            try {
                // Mock data for now
                this.events = [
                    {
                        id: 1,
                        title: 'Project Meeting',
                        start: '2024-01-15T10:00:00',
                        end: '2024-01-15T11:00:00',
                        type: 'meeting'
                    }
                ];
            } catch (error) {
                this.error = 'Failed to load events';
                console.error('Error loading events:', error);
            } finally {
                this.loading = false;
            }
        },
        
        changeView(view) {
            this.view = view;
        },
        
        navigateDate(direction) {
            // Navigate calendar
        }
    }));
    
    // Smart Search Data Function
    Alpine.data('smartSearch', () => ({
        query: '',
        suggestions: [],
        showSuggestions: false,
        recentSearches: [],
        
        init() {
            this.loadRecentSearches();
        },
        
        loadRecentSearches() {
            this.recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
        },
        
        search() {
            if (this.query.trim()) {
                this.addToRecentSearches(this.query);
                // Perform search
            }
        },
        
        addToRecentSearches(query) {
            this.recentSearches = this.recentSearches.filter(item => item !== query);
            this.recentSearches.unshift(query);
            this.recentSearches = this.recentSearches.slice(0, 5);
            localStorage.setItem('recentSearches', JSON.stringify(this.recentSearches));
        },
        
        clearRecentSearches() {
            this.recentSearches = [];
            localStorage.removeItem('recentSearches');
        }
    }));
    
    // Smart Filters Data Function
    Alpine.data('smartFilters', () => ({
        filters: {},
        activeFilters: [],
        savedViews: [],
        
        init() {
            this.loadSavedViews();
        },
        
        loadSavedViews() {
            this.savedViews = JSON.parse(localStorage.getItem('savedViews') || '[]');
        },
        
        saveView(name) {
            const view = {
                name,
                filters: { ...this.filters },
                timestamp: new Date().toISOString()
            };
            this.savedViews.unshift(view);
            this.savedViews = this.savedViews.slice(0, 10);
            localStorage.setItem('savedViews', JSON.stringify(this.savedViews));
        },
        
        loadView(view) {
            this.filters = { ...view.filters };
            this.updateActiveFilters();
        },
        
        updateActiveFilters() {
            this.activeFilters = Object.entries(this.filters)
                .filter(([key, value]) => value !== '' && value !== null)
                .map(([key, value]) => ({ key, value }));
        },
        
        clearFilters() {
            this.filters = {};
            this.activeFilters = [];
        }
    }));
    
    // Export Component Data Function
    Alpine.data('exportComponent', () => ({
        showExportModal: false,
        exportFormat: 'pdf',
        exportOptions: {
            includeCharts: true,
            includeData: true,
            includeFilters: false
        },
        
        openExportModal() {
            this.showExportModal = true;
        },
        
        closeExportModal() {
            this.showExportModal = false;
        },
        
        export() {
            // Export logic here
            this.closeExportModal();
        }
    }));
    
    // Analysis Drawer Data Function
    Alpine.data('analysisDrawer', () => ({
        isOpen: false,
        analysisType: 'overview',
        analysisData: {},
        
        open(type = 'overview') {
            this.analysisType = type;
            this.isOpen = true;
            this.loadAnalysisData();
        },
        
        close() {
            this.isOpen = false;
        },
        
        loadAnalysisData() {
            // Load analysis data based on type
            this.analysisData = {};
        }
    }));
    
    // Mobile Cards Data Function
    Alpine.data('mobileCards', () => ({
        cards: [],
        loading: false,
        
        init() {
            this.loadCards();
        },
        
        loadCards() {
            this.loading = true;
            // Load mobile cards data
            this.loading = false;
        }
    }));
    
    // Responsive Table Data Function
    Alpine.data('responsiveTable', () => ({
        columns: [],
        data: [],
        sortColumn: '',
        sortDirection: 'asc',
        loading: false,
        
        init() {
            this.loadData();
        },
        
        loadData() {
            this.loading = true;
            // Load table data
            this.loading = false;
        },
        
        sort(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            // Sort data
        }
    }));
    
    // Accessibility Focus Manager Data Function
    Alpine.data('accessibilityFocusManager', () => ({
        focusTrap: false,
        focusHistory: [],
        
        init() {
            this.setupFocusTrap();
        },
        
        setupFocusTrap() {
            // Setup focus trap for accessibility
        },
        
        manageFocus(element) {
            // Manage focus for accessibility
        }
    }));
    
    // Notification Component Data Function
    Alpine.data('notificationComponent', () => ({
        notifications: [],
        showNotifications: false,
        
        init() {
            this.loadNotifications();
        },
        
        loadNotifications() {
            // Load notifications
        },
        
        toggleNotifications() {
            this.showNotifications = !this.showNotifications;
        },
        
        markAsRead(notification) {
            // Mark notification as read
        },
        
        clearAll() {
            this.notifications = [];
        }
    }));
    
    // Navigation Component Data Function
    Alpine.data('navigationComponent', () => ({
        isOpen: false,
        currentPath: '',
        
        init() {
            this.currentPath = window.location.pathname;
        },
        
        toggle() {
            this.isOpen = !this.isOpen;
        },
        
        close() {
            this.isOpen = false;
        },
        
        navigate(path) {
            this.currentPath = path;
            window.location.href = path;
        }
    }));
    
    console.log('✅ All Alpine.js data functions loaded successfully');
});
