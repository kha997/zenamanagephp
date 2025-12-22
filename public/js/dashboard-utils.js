/**
 * ZenaManage Dashboard Utilities
 * Reusable JavaScript functions following design principles
 * 
 * Features:
 * - API-first architecture
 * - Error handling with retry logic
 * - Performance monitoring
 * - Accessibility support
 * - Mobile responsive
 */

// ==========================================================================
// Configuration
// ==========================================================================

const DASHBOARD_CONFIG = {
    API_BASE_URL: '/api/v1/app',
    DEBOUNCE_DELAY: 250,
    RETRY_ATTEMPTS: 3,
    RETRY_DELAY: 1000,
    CACHE_DURATION: 60000, // 60 seconds
    PERFORMANCE_BUDGET: {
        PAGE_LOAD: 500,
        API_RESPONSE: 300,
        KPI_UPDATE: 200
    }
};

// ==========================================================================
// API Utilities
// ==========================================================================

class DashboardAPI {
    constructor() {
        this.cache = new Map();
        this.requestQueue = new Map();
    }

    /**
     * Get tenant headers for API requests
     */
    getTenantHeaders() {
        const meta = document.querySelector('meta[name="x-tenant-id"]');
        return meta ? { 'X-Tenant-Id': meta.content } : {};
    }

    /**
     * Make API request with error handling and retry logic
     */
    async request(url, options = {}) {
        const startTime = performance.now();
        const cacheKey = `${url}_${JSON.stringify(options)}`;
        
        // Check cache first
        if (options.method === 'GET' && this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < DASHBOARD_CONFIG.CACHE_DURATION) {
                return cached.data;
            }
        }

        const defaultOptions = {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...this.getTenantHeaders()
            },
            credentials: 'include'
        };

        const requestOptions = { ...defaultOptions, ...options };

        for (let attempt = 1; attempt <= DASHBOARD_CONFIG.RETRY_ATTEMPTS; attempt++) {
            try {
                const response = await fetch(url, requestOptions);
                const endTime = performance.now();
                const duration = endTime - startTime;

                // Performance monitoring
                this.logPerformance(url, duration, response.status);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                // Cache successful GET requests
                if (options.method === 'GET') {
                    this.cache.set(cacheKey, {
                        data,
                        timestamp: Date.now()
                    });
                }

                return data;

            } catch (error) {
                console.warn(`API request attempt ${attempt} failed:`, error);

                if (attempt === DASHBOARD_CONFIG.RETRY_ATTEMPTS) {
                    throw error;
                }

                // Exponential backoff
                await this.delay(DASHBOARD_CONFIG.RETRY_DELAY * Math.pow(2, attempt - 1));
            }
        }
    }

    /**
     * Log performance metrics
     */
    logPerformance(url, duration, status) {
        if (duration > DASHBOARD_CONFIG.PERFORMANCE_BUDGET.API_RESPONSE) {
            console.warn(`Slow API response: ${url} took ${duration.toFixed(2)}ms`);
        }

        // Send to analytics if available
        if (window.gtag) {
            gtag('event', 'api_performance', {
                'url': url,
                'duration': Math.round(duration),
                'status': status
            });
        }
    }

    /**
     * Delay utility
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }

    /**
     * Get cached data
     */
    getCached(url) {
        const cacheKey = `${url}_${JSON.stringify({ method: 'GET' })}`;
        const cached = this.cache.get(cacheKey);
        return cached ? cached.data : null;
    }
}

// ==========================================================================
// KPI Management
// ==========================================================================

class KPIManager {
    constructor() {
        this.api = new DashboardAPI();
        this.kpis = new Map();
        this.updateInterval = null;
    }

    /**
     * Initialize KPI manager
     */
    init() {
        this.loadAllKPIs();
        this.setupAutoRefresh();
        this.setupEventListeners();
    }

    /**
     * Load all KPIs
     */
    async loadAllKPIs() {
        const kpiCards = document.querySelectorAll('.kpi-card[data-kpi]');
        const loadPromises = Array.from(kpiCards).map(card => {
            const kpiKey = card.dataset.kpi;
            return this.loadKPI(kpiKey);
        });

        await Promise.allSettled(loadPromises);
    }

    /**
     * Load individual KPI
     */
    async loadKPI(kpiKey) {
        try {
            this.showKPILoading(kpiKey);
            
            const data = await this.api.request(`${DASHBOARD_CONFIG.API_BASE_URL}/dashboard/kpis/${kpiKey}`);
            
            this.updateKPI(kpiKey, data);
            this.hideKPIStates(kpiKey);
            
        } catch (error) {
            console.error(`Failed to load KPI ${kpiKey}:`, error);
            this.showKPIError(kpiKey, error.message);
        }
    }

    /**
     * Update KPI display
     */
    updateKPI(kpiKey, data) {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (!card) return;

        // Update value
        const valueElement = card.querySelector('.kpi-value');
        if (valueElement) {
            valueElement.textContent = data.value ?? '—';
        }

        // Update trend
        const trendElement = card.querySelector('.kpi-trend');
        if (trendElement && data.trend) {
            trendElement.textContent = data.trend;
            trendElement.className = `kpi-trend text-xs ${this.getTrendClass(data.trend_type)}`;
        }

        // Store KPI data
        this.kpis.set(kpiKey, data);
    }

    /**
     * Show loading state
     */
    showKPILoading(kpiKey) {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            card.querySelector('.kpi-loading')?.classList.remove('hidden');
            card.querySelector('.kpi-error')?.classList.add('hidden');
        }
    }

    /**
     * Show error state
     */
    showKPIError(kpiKey, errorMessage = 'Failed to load data') {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            card.querySelector('.kpi-loading')?.classList.add('hidden');
            card.querySelector('.kpi-error')?.classList.remove('hidden');
            const errorText = card.querySelector('.kpi-error p');
            if (errorText) {
                errorText.textContent = errorMessage;
            }
        }
    }

    /**
     * Hide loading/error states
     */
    hideKPIStates(kpiKey) {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            card.querySelector('.kpi-loading')?.classList.add('hidden');
            card.querySelector('.kpi-error')?.classList.add('hidden');
        }
    }

    /**
     * Get trend CSS class
     */
    getTrendClass(trendType) {
        const trendClasses = {
            'positive': 'text-green-600',
            'negative': 'text-red-600',
            'neutral': 'text-gray-600'
        };
        return trendClasses[trendType] || trendClasses['neutral'];
    }

    /**
     * Setup auto-refresh
     */
    setupAutoRefresh() {
        // Refresh every 5 minutes
        this.updateInterval = setInterval(() => {
            this.loadAllKPIs();
        }, 5 * 60 * 1000);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Retry button clicks
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="retry"]')) {
                const card = e.target.closest('.kpi-card');
                if (card) {
                    const kpiKey = card.dataset.kpi;
                    this.loadKPI(kpiKey);
                }
            }
        });

        // KPI card clicks for analytics
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.kpi-card[data-kpi]');
            if (card && !e.target.closest('a, button')) {
                const kpiKey = card.dataset.kpi;
                const kpiLabel = card.querySelector('.kpi-label')?.textContent;
                
                if (window.gtag) {
                    gtag('event', 'kpi_card_click', {
                        'kpi_key': kpiKey,
                        'kpi_label': kpiLabel
                    });
                }
            }
        });
    }

    /**
     * Destroy KPI manager
     */
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }
}

// ==========================================================================
// Search Management
// ==========================================================================

class SearchManager {
    constructor() {
        this.api = new DashboardAPI();
        this.debounceTimeout = null;
        this.selectedIndex = -1;
        this.results = [];
    }

    /**
     * Initialize search manager
     */
    init() {
        this.setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Global keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openSearch();
            }
        });

        // Search input
        const searchInput = document.getElementById('kbdInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.debouncedSearch(e.target.value);
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateResults('down');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateResults('up');
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.selectResult();
                }
            });
        }
    }

    /**
     * Open search modal
     */
    openSearch() {
        const modal = document.getElementById('search-modal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                const input = document.getElementById('kbdInput');
                if (input) {
                    input.focus();
                }
            }, 100);
        }
    }

    /**
     * Close search modal
     */
    closeSearch() {
        const modal = document.getElementById('search-modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            this.resetSearch();
        }
    }

    /**
     * Reset search state
     */
    resetSearch() {
        this.results = [];
        this.selectedIndex = -1;
        
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }
    }

    /**
     * Debounced search
     */
    debouncedSearch(query) {
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }

        this.debounceTimeout = setTimeout(() => {
            this.search(query);
        }, DASHBOARD_CONFIG.DEBOUNCE_DELAY);
    }

    /**
     * Perform search
     */
    async search(query) {
        if (!query.trim()) {
            this.results = [];
            this.updateResults();
            return;
        }

        try {
            const data = await this.api.request(`${DASHBOARD_CONFIG.API_BASE_URL}/search?q=${encodeURIComponent(query)}`);
            this.results = this.formatResults(data);
            this.selectedIndex = -1;
            this.updateResults();
            
            // Track search
            if (window.gtag) {
                gtag('event', 'search', {
                    'search_term': query,
                    'results_count': this.results.length
                });
            }
            
        } catch (error) {
            console.error('Search error:', error);
            this.results = [];
            this.updateResults();
        }
    }

    /**
     * Format search results
     */
    formatResults(data) {
        const results = [];
        
        // Add projects
        if (data.projects) {
            data.projects.forEach(project => {
                results.push({
                    id: `project-${project.id}`,
                    title: project.name,
                    subtitle: project.description || 'Project',
                    type: 'Project',
                    icon: 'fas fa-project-diagram',
                    url: `/app/projects/${project.id}`
                });
            });
        }
        
        // Add tasks
        if (data.tasks) {
            data.tasks.forEach(task => {
                results.push({
                    id: `task-${task.id}`,
                    title: task.title,
                    subtitle: task.project_name || 'Task',
                    type: 'Task',
                    icon: 'fas fa-tasks',
                    url: `/app/tasks/${task.id}`
                });
            });
        }
        
        // Add users
        if (data.users) {
            data.users.forEach(user => {
                results.push({
                    id: `user-${user.id}`,
                    title: user.name,
                    subtitle: user.role || 'Team Member',
                    type: 'User',
                    icon: 'fas fa-user',
                    url: `/app/team/${user.id}`
                });
            });
        }
        
        return results.slice(0, 10); // Limit to 10 results
    }

    /**
     * Update results display
     */
    updateResults() {
        const resultsContainer = document.querySelector('[data-zena-search-results]');
        if (!resultsContainer) return;

        if (this.results.length === 0) {
            resultsContainer.innerHTML = '<li class="px-2 py-2 text-sm text-gray-500">No results found</li>';
            return;
        }

        resultsContainer.innerHTML = this.results.map((result, index) => `
            <li>
                <a href="${result.url}" 
                   class="search-result-item flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                   ${index === this.selectedIndex ? 'aria-selected="true"' : ''}
                   role="option">
                    <div class="flex-shrink-0">
                        <i class="${result.icon} text-gray-500" aria-hidden="true"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-medium text-gray-900 truncate">${this.escapeHtml(result.title)}</h4>
                        <p class="text-sm text-gray-500 truncate">${this.escapeHtml(result.subtitle)}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="text-xs text-gray-400">${this.escapeHtml(result.type)}</span>
                    </div>
                </a>
            </li>
        `).join('');
    }

    /**
     * Navigate results with keyboard
     */
    navigateResults(direction) {
        if (this.results.length === 0) return;
        
        if (direction === 'down') {
            this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
        } else if (direction === 'up') {
            this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
        }
        
        this.updateResults();
        
        // Scroll selected item into view
        if (this.selectedIndex >= 0) {
            const selectedItem = document.querySelector(`[aria-selected="true"]`);
            if (selectedItem) {
                selectedItem.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    /**
     * Select result
     */
    selectResult() {
        if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
            window.location.href = this.results[this.selectedIndex].url;
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// ==========================================================================
// Performance Monitoring
// ==========================================================================

class PerformanceMonitor {
    constructor() {
        this.metrics = new Map();
        this.observers = new Map();
    }

    /**
     * Initialize performance monitoring
     */
    init() {
        this.setupPageLoadMonitoring();
        this.setupResourceMonitoring();
        this.setupUserInteractionMonitoring();
    }

    /**
     * Setup page load monitoring
     */
    setupPageLoadMonitoring() {
        window.addEventListener('load', () => {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation) {
                const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
                this.recordMetric('page_load', loadTime);
                
                if (loadTime > DASHBOARD_CONFIG.PERFORMANCE_BUDGET.PAGE_LOAD) {
                    console.warn(`Slow page load: ${loadTime.toFixed(2)}ms`);
                }
            }
        });
    }

    /**
     * Setup resource monitoring
     */
    setupResourceMonitoring() {
        const observer = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (entry.duration > 1000) { // 1 second threshold
                    console.warn(`Slow resource: ${entry.name} took ${entry.duration.toFixed(2)}ms`);
                }
            }
        });
        
        observer.observe({ entryTypes: ['resource'] });
        this.observers.set('resource', observer);
    }

    /**
     * Setup user interaction monitoring
     */
    setupUserInteractionMonitoring() {
        let interactionStart = 0;
        
        document.addEventListener('click', (e) => {
            interactionStart = performance.now();
        });
        
        document.addEventListener('keydown', (e) => {
            interactionStart = performance.now();
        });
        
        // Measure time to next paint after interaction
        requestAnimationFrame(() => {
            const interactionTime = performance.now() - interactionStart;
            if (interactionTime > 100) { // 100ms threshold
                console.warn(`Slow interaction response: ${interactionTime.toFixed(2)}ms`);
            }
        });
    }

    /**
     * Record performance metric
     */
    recordMetric(name, value) {
        if (!this.metrics.has(name)) {
            this.metrics.set(name, []);
        }
        
        this.metrics.get(name).push({
            value,
            timestamp: Date.now()
        });
        
        // Send to analytics if available
        if (window.gtag) {
            gtag('event', 'performance_metric', {
                'metric_name': name,
                'metric_value': Math.round(value)
            });
        }
    }

    /**
     * Get performance metrics
     */
    getMetrics(name) {
        return this.metrics.get(name) || [];
    }

    /**
     * Clear metrics
     */
    clearMetrics() {
        this.metrics.clear();
    }

    /**
     * Destroy performance monitor
     */
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers.clear();
    }
}

// ==========================================================================
// Accessibility Utilities
// ==========================================================================

class AccessibilityManager {
    constructor() {
        this.focusHistory = [];
        this.currentFocus = null;
    }

    /**
     * Initialize accessibility manager
     */
    init() {
        this.setupFocusManagement();
        this.setupKeyboardNavigation();
        this.setupScreenReaderSupport();
    }

    /**
     * Setup focus management
     */
    setupFocusManagement() {
        document.addEventListener('focusin', (e) => {
            this.focusHistory.push(e.target);
            this.currentFocus = e.target;
        });

        document.addEventListener('focusout', (e) => {
            this.currentFocus = null;
        });
    }

    /**
     * Setup keyboard navigation
     */
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Escape key handling
            if (e.key === 'Escape') {
                this.handleEscape();
            }
            
            // Tab navigation enhancement
            if (e.key === 'Tab') {
                this.handleTabNavigation(e);
            }
        });
    }

    /**
     * Setup screen reader support
     */
    setupScreenReaderSupport() {
        // Announce dynamic content changes
        this.createAnnouncer();
        
        // Enhance form labels
        this.enhanceFormLabels();
        
        // Add ARIA live regions
        this.addLiveRegions();
    }

    /**
     * Create screen reader announcer
     */
    createAnnouncer() {
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        announcer.id = 'screen-reader-announcer';
        document.body.appendChild(announcer);
    }

    /**
     * Announce message to screen readers
     */
    announce(message) {
        const announcer = document.getElementById('screen-reader-announcer');
        if (announcer) {
            announcer.textContent = message;
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        }
    }

    /**
     * Handle escape key
     */
    handleEscape() {
        // Close modals
        const modals = document.querySelectorAll('[role="dialog"]:not(.hidden)');
        if (modals.length > 0) {
            const lastModal = modals[modals.length - 1];
            const closeButton = lastModal.querySelector('[aria-label*="Close"], [aria-label*="close"]');
            if (closeButton) {
                closeButton.click();
            }
        }
    }

    /**
     * Handle tab navigation
     */
    handleTabNavigation(e) {
        // Trap focus in modals
        const modal = document.querySelector('[role="dialog"]:not(.hidden)');
        if (modal) {
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            if (focusableElements.length === 0) return;
            
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (e.shiftKey) {
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        }
    }

    /**
     * Enhance form labels
     */
    enhanceFormLabels() {
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')) {
                const label = document.querySelector(`label[for="${input.id}"]`);
                if (label) {
                    input.setAttribute('aria-labelledby', label.id || `label-${input.id}`);
                }
            }
        });
    }

    /**
     * Add ARIA live regions
     */
    addLiveRegions() {
        // Status updates
        const statusRegion = document.createElement('div');
        statusRegion.setAttribute('aria-live', 'polite');
        statusRegion.setAttribute('aria-atomic', 'true');
        statusRegion.className = 'sr-only';
        statusRegion.id = 'status-announcer';
        document.body.appendChild(statusRegion);
        
        // Alert updates
        const alertRegion = document.createElement('div');
        alertRegion.setAttribute('aria-live', 'assertive');
        alertRegion.setAttribute('aria-atomic', 'true');
        alertRegion.className = 'sr-only';
        alertRegion.id = 'alert-announcer';
        document.body.appendChild(alertRegion);
    }
}

// ==========================================================================
// Dashboard Manager (Main Controller)
// ==========================================================================

class DashboardManager {
    constructor() {
        this.api = new DashboardAPI();
        this.kpiManager = new KPIManager();
        this.searchManager = new SearchManager();
        this.performanceMonitor = new PerformanceMonitor();
        this.accessibilityManager = new AccessibilityManager();
        this.initialized = false;
    }

    /**
     * Initialize dashboard
     */
    async init() {
        if (this.initialized) return;
        
        try {
            console.log('🚀 Initializing ZenaManage Dashboard...');
            
            // Initialize all managers
            this.performanceMonitor.init();
            this.accessibilityManager.init();
            this.kpiManager.init();
            this.searchManager.init();
            
            // Setup global event listeners
            this.setupGlobalEventListeners();
            
            // Load initial data
            await this.loadInitialData();
            
            this.initialized = true;
            console.log('✅ Dashboard initialized successfully');
            
        } catch (error) {
            console.error('❌ Failed to initialize dashboard:', error);
            throw error;
        }
    }

    /**
     * Setup global event listeners
     */
    setupGlobalEventListeners() {
        // Refresh button
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="refresh"]') || 
                e.target.closest('[data-action="refresh"]')) {
                e.preventDefault();
                this.refreshAll();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshAll();
            }
        });
        
        // Window focus/blur
        window.addEventListener('focus', () => {
            this.kpiManager.loadAllKPIs();
        });
        
        // Online/offline status
        window.addEventListener('online', () => {
            this.showToast('Connection restored', 'success');
            this.kpiManager.loadAllKPIs();
        });
        
        window.addEventListener('offline', () => {
            this.showToast('Connection lost', 'warning');
        });
    }

    /**
     * Load initial data
     */
    async loadInitialData() {
        const startTime = performance.now();
        
        try {
            // Load KPIs
            await this.kpiManager.loadAllKPIs();
            
            // Load other dashboard components
            await this.loadDashboardComponents();
            
            const endTime = performance.now();
            const duration = endTime - startTime;
            
            this.performanceMonitor.recordMetric('initial_load', duration);
            
            if (duration > DASHBOARD_CONFIG.PERFORMANCE_BUDGET.PAGE_LOAD) {
                console.warn(`Slow initial load: ${duration.toFixed(2)}ms`);
            }
            
        } catch (error) {
            console.error('Failed to load initial data:', error);
            throw error;
        }
    }

    /**
     * Load dashboard components
     */
    async loadDashboardComponents() {
        const promises = [];
        
        // Load meetings
        promises.push(this.loadMeetings());
        
        // Load notifications
        promises.push(this.loadNotifications());
        
        await Promise.allSettled(promises);
    }

    /**
     * Load meetings
     */
    async loadMeetings() {
        try {
            const data = await this.api.request(`${DASHBOARD_CONFIG.API_BASE_URL}/calendar/events?range=today..+7`);
            this.renderMeetings(data.data || data || []);
        } catch (error) {
            console.warn('Failed to load meetings:', error);
        }
    }

    /**
     * Load notifications
     */
    async loadNotifications() {
        try {
            const data = await this.api.request(`${DASHBOARD_CONFIG.API_BASE_URL}/notifications?unread=true`);
            this.renderNotifications(data.data || data || []);
        } catch (error) {
            console.warn('Failed to load notifications:', error);
        }
    }

    /**
     * Render meetings
     */
    renderMeetings(meetings) {
        const container = document.querySelector('[data-zena-meetings]');
        if (!container) return;
        
        container.innerHTML = meetings.slice(0, 4).map(meeting => `
            <li>
                <div class="flex items-center gap-3">
                    <div class="avatar">
                        <div class="rounded-field size-10">
                            <img src="${meeting.avatar_url || 'https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png'}" alt="avatar"/>
                        </div>
                    </div>
                    <div class="grow">
                        <h6 class="text-base-content mb-px font-medium">${this.escapeHtml(meeting.title || 'Meeting')}</h6>
                        <div class="text-base-content/50 flex items-center gap-1 text-sm">
                            <span class="icon-[tabler--calendar] size-4.5"></span>
                            <span>${meeting.starts_at} - ${meeting.ends_at}</span>
                        </div>
                    </div>
                    <span class="badge badge-soft">${this.escapeHtml(meeting.label || 'Event')}</span>
                </div>
            </li>
        `).join('');
    }

    /**
     * Render notifications
     */
    renderNotifications(notifications) {
        const badge = document.querySelector('#notification-dropdown .badge-primary');
        if (badge) {
            badge.textContent = `${notifications.length} New`;
        }
        
        const container = document.querySelector('#tabs-basic-1 ul');
        if (!container) return;
        
        container.innerHTML = notifications.slice(0, 5).map(notification => `
            <li>
                <div class="flex w-full items-center gap-3 py-3">
                    <div class="avatar"><div class="size-10 rounded-full">
                        <img src="${notification.actor_avatar || 'https://cdn.flyonui.com/fy-assets/avatar/avatar-2.png'}" alt="avatar" />
                    </div></div>
                    <div class="flex-1">
                        <h6 class="text-base-content mb-0.5 font-medium">${this.escapeHtml(notification.title || 'Notification')}</h6>
                        <div class="flex items-center gap-x-2.5">
                            <p class="text-base-content/50 text-sm">${this.escapeHtml(notification.time_ago || '')}</p>
                            <span class="bg-neutral/20 size-1.5 rounded-full"></span>
                            <p class="text-base-content/50 text-sm">${this.escapeHtml(notification.category || '')}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-center gap-3">
                        <button class="btn btn-xs btn-circle btn-text" data-zena-dismiss="${this.escapeHtml(notification.id)}">
                            <span class="icon-[tabler--x] text-base-content/80 size-4"></span>
                        </button>
                        <div class="bg-primary size-1.5 rounded-full"></div>
                    </div>
                </div>
            </li>
            <li><hr class="border-base-content/20 -mx-3 my-1.5" /></li>
        `).join('');
    }

    /**
     * Refresh all data
     */
    async refreshAll() {
        try {
            this.api.clearCache();
            await this.loadInitialData();
            this.showToast('Dashboard refreshed successfully', 'success');
        } catch (error) {
            console.error('Failed to refresh dashboard:', error);
            this.showToast('Failed to refresh dashboard', 'error');
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            type === 'warning' ? 'bg-yellow-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Announce to screen readers
        this.accessibilityManager.announce(message);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Destroy dashboard
     */
    destroy() {
        this.kpiManager.destroy();
        this.performanceMonitor.destroy();
        this.initialized = false;
    }
}

// ==========================================================================
// Global Initialization
// ==========================================================================

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dashboardManager = new DashboardManager();
    window.dashboardManager.init().catch(error => {
        console.error('Failed to initialize dashboard:', error);
    });
});

// Export for global use
window.DashboardManager = DashboardManager;
window.DashboardAPI = DashboardAPI;
window.KPIManager = KPIManager;
window.SearchManager = SearchManager;
window.PerformanceMonitor = PerformanceMonitor;
window.AccessibilityManager = AccessibilityManager;
