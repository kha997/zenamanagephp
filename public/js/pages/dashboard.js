/**
 * Dashboard Page Module - Soft Refresh Implementation with SWR
 */

// import { getWithETag } from '/js/shared/swr.js'; // Converted to regular script

const API = {
  summary: (range='30d') => `/api/admin/dashboard/summary?range=${range}`,
  charts:  (range='30d') => `/api/admin/dashboard/charts?range=${range}`,
  activity: () => `/api/admin/dashboard/activity`,
  exportSignups: (range='30d') => `/api/admin/dashboard/signups/export.csv?range=${range}`,
  exportErrors:  (range='30d') => `/api/admin/dashboard/errors/export.csv?range=${range}`,
};

class DashboardManager {
    constructor() {
        this.abortController = null;
        this.isRefreshing = false;
        this.lastRefreshTime = null;
        this.init();
    }

    init() {
        console.log('[Dashboard] Initializing...');
        document.addEventListener('soft-refresh:triggered', this.handleSoftRefresh.bind(this));
    }

    async handleSoftRefresh(event) {
        if (event.detail?.route !== 'dashboard') return;
        console.log('[Dashboard] Soft refresh triggered');
        await this.refresh();
    }

    async refresh(range='30d') {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        
        try {
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();
            
            this.updateRefreshIndicator();
            
            // Set panel loading (dim local panels)
            this.setPanelLoading('signups-panel', true);
            this.setPanelLoading('errors-panel', true);
            
            // Load with SWR caching
            await Promise.allSettled([
                this.loadSummary(range),
                this.loadCharts(range),
                this.loadActivity()
            ]);
            
            document.dispatchEvent(new CustomEvent('dashboard:refreshed', {
                detail: { timestamp: this.lastRefreshTime }
            }));
            
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('[Dashboard] Refresh failed:', error);
            }
        } finally {
            this.isRefreshing = false;
            this.setPanelLoading('signups-panel', false);
            this.setPanelLoading('errors-panel', false);
            this.abortController = null;
        }
    }

    async loadSummary(range) {
        try {
            const data = await window.SWRCache.getWithETag(
                `dashboard-summary-${range}`, 
                API.summary(range), 
                { signal: this.abortController.signal }
            );
            this.updateKPIData(data);
        } catch (error) {
            console.error('[Dashboard] Failed to load summary:', error);
        }
    }

    async loadCharts(range) {
        try {
            const data = await window.SWRCache.getWithETag(
                `dashboard-charts-${range}`, 
                API.charts(range), 
                { signal: this.abortController.signal }
            );
            this.updateChartData(data);
        } catch (error) {
            console.error('[Dashboard] Failed to load charts:', error);
        }
    }

    async loadActivity() {
        try {
            const data = await window.SWRCache.getWithETag(
                'dashboard-activity', 
                API.activity(), 
                { signal: this.abortController.signal }
            );
            this.updateActivityData(data);
        } catch (error) {
            console.error('[Dashboard] Failed to load activity:', error);
        }
    }

    updateKPIData(data) {
        document.dispatchEvent(new CustomEvent('dashboard:kpisUpdated', { detail: { data } }));
    }

    updateChartData(data) {
        document.dispatchEvent(new CustomEvent('dashboard:chartsUpdated', { detail: { data } }));
    }

    updateActivityData(data) {
        document.dispatchEvent(new CustomEvent('dashboard:activityUpdated', { detail: { data } }));
    }

    updateRefreshIndicator() {
        this.lastRefreshTime = new Date();
        document.querySelectorAll('.refresh-indicator span').forEach(element => {
            element.textContent = this.lastRefreshTime.toLocaleTimeString();
        });
    }

    setPanelLoading(panelId, loading) {
        const panel = document.getElementById(panelId);
        if (!panel) return;
        
        if (loading) {
            panel.classList.add('soft-dim');
        } else {
            panel.classList.remove('soft-dim');
        }
    }

    async exportChart(type, range = '30d') {
        try {
            const url = API[`export${type.charAt(0).toUpperCase() + type.slice(1)}`](range);
            const response = await fetch(url);
            
            if (!response.ok) {
                if (response.status === 429) {
                    const retryAfter = response.headers.get('Retry-After');
                    throw new Error(`Rate limited. Retry after ${retryAfter || 60} seconds.`);
                }
                throw new Error('Export failed');
            }
            
            const blob = await response.blob();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${type}-${range}.csv`;
            link.click();
        } catch (error) {
            alert(`Export failed: ${error.message}`);
        }
    }
}

// Initialize global dashboard instance
window.dashboard = new DashboardManager();

// Export methods to global scope for easy access
window.dashboard.refresh = (range) => window.dashboard.refresh(range);
window.dashboard.exportSignups = (range) => window.dashboard.exportChart('signups', range);
window.dashboard.exportErrors = (range) => window.dashboard.exportChart('errors', range);

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const range = new URLSearchParams(window.location.search).get('range') || '30d';
    window.dashboard.refresh(range);
});

console.log('[Dashboard] Module loaded');