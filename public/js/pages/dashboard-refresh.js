// Dashboard Refresh Hook Template
// Copy this content to pages that need refresh functionality

const inflight = {};
function setReloading(on) { 
    if (window.AdminRefresh?.setLoading) {
        window.AdminRefresh.setLoading(on);
    } else {
        document.body.classList.toggle('page-reloading', !!on);
    }
}

async function fetchAll() {
    // Abort previous dashboard requests
    inflight.kpis?.abort?.();
    inflight.activities?.abort?.();
    inflight.stats?.abort?.();
    
    // Create new abort controllers
    inflight.kpis = new AbortController();
    inflight.activities = new AbortController();
    inflight.stats = new AbortController();
    
    try {
        // Replace endpoints with actual dashboard APIs
        const [kpisResult, activitiesResult, statsResult] = await Promise.allSettled([
            fetchJSON('/api/admin/dashboard/kpis', {signal: inflight.kpis.signal, silent:true}),
            fetchJSON('/api/admin/dashboard/activities', {signal: inflight.activities.signal, silent:true}),
            fetchJSON('/api/admin/dashboard/stats', {signal: inflight.stats.signal, silent:true})
        ]);
        
        // Update UI with new data (preserve DOM structure)
        if (kpisResult.status === 'fulfilled') {
            updateKPIs(kpisResult.value.data);
        }
        
        if (activitiesResult.status === 'fulfilled') {
            updateActivities(activitiesResult.value.data);
        }
        
        if (statsResult.status === 'fulfilled') {
            updateStats(statsResult.value.data);
        }
        
        // Log refresh completion
        console.log('Dashboard refresh completed');
        
    } catch (error) {
        console.error('Dashboard refresh error:', error);
        showRefreshError(error.message);
    }
}

// UI update functions (customize per page)
function updateKPIs(data) {
    // Update KPI cards with new data
    document.querySelectorAll('[data-kpi]').forEach(element => {
        const kpiType = element.dataset.kpi;
        if (data[kpiType]) {
            const valueEl = element.querySelector('.kpi-value');
            const deltaEl = element.querySelector('.kpi-delta');
            
            if (valueEl) valueEl.textContent = data[kpiType].value || 0;
            if (deltaEl) {
                deltaEl.textContent = `${data[kpiType].delta > 0 ? '+' : ''}${data[kpiType].delta || 0}%`;
                deltaEl.className = deltaEl.className.replace(/text-\w+/, 
                    data[kpiType].delta > 0 ? 'text-green-600' : 
                    data[kpiType].delta < 0 ? 'text-red-600' : 'text-gray-600'
                );
            }
        }
    });
}

function updateActivities(data) {
    // Update activity feed
    const feedContainer = document.querySelector('[data-activity-feed]');
    if (feedContainer && data) {
        // Rebuild activity list while preserving DOM structure
        const template = document.querySelector('#activity-item-template');
        if (template) {
            feedContainer.innerHTML = '';
            (data || []).forEach(activity => {
                const clone = template.content.cloneNode(true);
                clone.querySelector('.activity-text').textContent = activity.message;
                clone.querySelector('.activity-time').textContent = new Date(activity.created_at).toLocaleTimeString();
                feedContainer.appendChild(clone);
            });
        }
    }
}

function updateStats(data) {
    // Update statistics charts/sections
    // This would typically update any charts or statistics displays
    console.log('Stats updated:', data);
}

function showRefreshError(message) {
    // Show error state without disrupting UI
    const errorContainer = document.querySelector('[data-error-container]');
    if (errorContainer) {
        errorContainer.innerHTML = `<div class="text-yellow-600 text-sm">Refresh failed: ${message}</div>`;
        setTimeout(() => errorContainer.innerHTML = '', 3000);
    }
}

// Global Dashboard namespace
window.Dashboard = window.Dashboard || {};
window.Dashboard.refresh = async function() {
    setReloading(true);
    try {
        await fetchAll();
    } finally {
        setReloading(false);
    }
};

// Auto-refresh on page load with idle callback
document.addEventListener('DOMContentLoaded', () => {
    // Render from cache first if available (fast paint)
    // renderFromCache(); // Implement per page
    
    // Then refresh in background
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => window.Dashboard?.refresh(), { timeout: 700 });
    } else {
        setTimeout(() => window.Dashboard?.refresh(), 100);
    }
});

// Auto-import fetchJSON if not already available
if (typeof window.fetchJSON === 'undefined') {
    console.warn('fetchJSON not available, auto-importing...');
    import('/js/core/swr-fetcher.js').then(module => {
        window.fetchJSON = module.fetchJSON;
        console.log('fetchJSON imported successfully');
    }).catch(error => {
        console.error('Failed to import fetchJSON:', error);
    });
}
