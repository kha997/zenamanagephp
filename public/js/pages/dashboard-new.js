/**
 * Dashboard Module - Simplified with SWR + ETag
 * Use shared SWR from Security page pattern
 */

// Dashboard Charts Class
class DashboardCharts {
    constructor(options) {
        this.signupsChart = null;
        this.errorsChart = null;
        this.signupsEl = options.signupsEl;
        this.errorsEl = options.errorsEl;
    }

    render(data, range) {
        this.destroy();
        
        // Render signups chart
        if (this.signupsEl && data.signups) {
            this.signupsChart = new Chart(this.signupsEl, {
                type: 'line',
                data: {
                    labels: data.signups.labels || [],
                    datasets: [{
                        label: 'New Signups',
                        data: data.signups.datasets?.[0]?.data || [],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: (context) => {
                                    return new Date(context[0].label).toLocaleDateString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            grid: { display: false },
                            ticks: {
                                maxTicksLimit: 7,
                                callback: (value) => {
                                    return new Date(value).toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric' 
                                    });
                                }
                            }
                        },
                        y: {
                            display: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.1)' },
                            ticks: { padding: 10, precision: 0 }
                        }
                    },
                    animation: { duration: 800 }
                }
            });
        }

        // Render errors chart
        if (this.errorsEl && data.error_rate) {
            this.errorsChart = new Chart(this.errorsEl, {
                type: 'bar',
                data: {
                    labels: data.error_rate.labels || [],
                    datasets: [{
                        label: '# Errors',
                        data: data.error_rate.datasets?.[0]?.data || [],
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: '#EF4444',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                        hoverBackgroundColor: 'rgba(239, 68, 68, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: (context) => {
                                    return new Date(context[0].label).toLocaleDateString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            grid: { display: false },
                            ticks: {
                                maxTicksLimit: 7,
                                callback: (value) => {
                                    return new Date(value).toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric' 
                                    });
                                }
                            }
                        },
                        y: {
                            display: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.1)' },
                            ticks: { padding: 10, precision: 0 }
                        }
                    },
                    animation: { duration: 800 }
                }
            });
        }
    }

    destroy() {
        if (this.signupsChart) {
            this.signupsChart.destroy();
            this.signupsChart = null;
        }
        if (this.errorsChart) {
            this.errorsChart.destroy();
            this.errorsChart = null;
        }
    }
}

// SWR helper - simplified version using Security pattern
async function getWithETag(url, options = {}) {
    const { signal, cacheKey } = options;
    
    // Simple caching based on URL
    const etagKey = `dashboard_etag_${url}`;
    const dataKey = `dashboard_data_${url}`;
    
    const cachedEtag = localStorage.getItem(etagKey);
    const cachedData = cachedEtag ? JSON.parse(localStorage.getItem(dataKey) || 'null') : null;
    
    const headers = { 'Accept': 'application/json' };
    if (cachedEtag) {
        headers['If-None-Match'] = cachedEtag;
    }
    
    try {
        const response = await fetch(url, {
            headers,
            signal: signal
        });
        
        // Handle 304 Not Modified
        if (response.status === 304 && cachedData) {
            console.log('[SWR] Cache hit (304)');
            return { status: 304, data: cachedData, fromCache: true };
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        const etag = response.headers.get('ETag');
        
        // Cache the response
        if (etag) {
            localStorage.setItem(etagKey, etag);
            localStorage.setItem(dataKey, JSON.stringify(data));
            
            // Clean old cache after 30 seconds
            setTimeout(() => {
                localStorage.removeItem(etagKey);
                localStorage.removeItem(dataKey);
            }, 30000);
        }
        
        return { status: response.status, data: data, fromCache: false };
        
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }
        
        // Fall back to cache on network error
        if (cachedData) {
            console.warn('[SWR] Network error, using cache');
            return { status: 0, data: cachedData, fromCache: true };
        }
        
        throw error;
    }
}

// Dashboard Manager
const state = { 
    aborter: null, 
    charts: null 
};

window.dashboard = {
    async refresh(range = '30d') {
        // Cancel previous requests
        if (state.aborter) state.aborter.abort();
        state.aborter = new AbortController();
        
        // Show loading state
        document.getElementById('signups-panel')?.classList.add('soft-dim');
        document.getElementById('errors-panel')?.classList.add('soft-dim');

        try {
            const results = await Promise.all([
                getWithETag(`/api/admin/dashboard/summary?range=${range}`, 
                { signal: state.aborter.signal }),
                getWithETag(`/api/admin/dashboard/charts?range=${range}`, 
                { signal: state.aborter.signal })
            ]);

            const [summary, charts] = results;

            // Update KPIs + sparklines
            this.updateKpis(summary.data);

            // Render charts
            state.charts?.destroy();
            state.charts = new DashboardCharts({
                signupsEl: document.getElementById('chart-signups'),
                errorsEl: document.getElementById('chart-errors')
            });
            state.charts.render(charts.data, range);
            
            console.log('✅ Dashboard refreshed successfully');
            
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('❌ Dashboard refresh failed:', error);
                this.showError('Refresh failed: ' + error.message);
            }
        } finally {
            // Hide loading state
            document.getElementById('signups-panel')?.classList.remove('soft-dim');
            document.getElementById('errors-panel')?.classList.remove('soft-dim');
        }
    },

    updateKpis(data) {
        // Update Alpine.js data
        const container = document.querySelector('[x-data*="adminDashboard"]');
        if (!container?.__x) return;
        
        const alpineData = container.__x.getData();
        
        // Update KPIs
        alpineData.kpis = {
            totalTenants: {
                value: data.tenants.total,
                deltaPct: data.tenants.growth_rate,
                period: 'last month'
            },
            totalUsers: {
                value: data.users.total,
                deltaPct: data.users.growth_rate,
                period: 'last month'
            },
            errors24h: {
                value: data.errors.last_24h,
                deltaAbs: data.errors.change_from_yesterday
            },
            queueJobs: {
                value: data.queue.active_jobs,
                status: data.queue.status
            },
            storage: {
                usedBytes: data.storage.used_bytes,
                capacityBytes: data.storage.capacity_bytes
            }
        };

        // Update refresh timestamp
        alpineData.lastRefresh = new Date().toLocaleTimeString();

        // Trigger Alpine reactivity
        container.__x.run();

        // Render sparklines
        this.renderSparklines(data);
    },

    renderSparklines(data) {
        const kpiMap = {
            'tenants': 'tenantsSparkline',
            'users': 'usersSparkline', 
            'errors': 'errorsSparkline',
            'queue': 'queueSparkline',
            'storage': 'storageSparkline'
        };

        Object.keys(kpiMap).forEach(key => {
            const sparklineData = data[key]?.sparkline;
            if (sparklineData && Array.isArray(sparklineData) && sparklineData.length > 0) {
                const canvasId = kpiMap[key];
                const canvas = document.getElementById(canvasId);
                
                if (canvas) {
                    console.log(`🎨 Rendering sparkline for ${key}:`, sparklineData.length, 'points');
                    this.renderSparkline(canvas, sparklineData, this.getSparklineColor(key));
                } else {
                    console.warn(`Canvas ${canvasId} not found for ${key}`);
                }
            } else {
                // Render with mock data if no real data
                const canvasId = kpiMap[key];
                const canvas = document.getElementById(canvasId);
                if (canvas) {
                    const mockData = this.generateMockSparkline(key);
                    this.renderSparkline(canvas, mockData, this.getSparklineColor(key));
                }
            }
        });
    },

    generateMockSparkline(type) {
        // Generate realistic mock data based on type
        const baseValues = {
            'tenants': [89, 91, 88, 92, 94, 91, 95],
            'users': [1247, 1258, 1263, 1278, 1295, 1289, 1298],
            'errors': [12, 8, 15, 6, 9, 11, 14],
            'queue': [156, 142, 168, 134, 179, 158, 165],
            'storage': [75, 72, 78, 76, 81, 79, 84]
        };
        
        return baseValues[type] || Array.from({length: 7}, () => Math.round(Math.random() * 10));
    },

    renderSparkline(canvas, data, color) {
        const ctx = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        
        canvas.width = rect.width * window.devicePixelRatio;
        canvas.height = rect.height * window.devicePixelRatio;
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';

        if (!data || data.length < 2) return;

        ctx.clearRect(0, 0, rect.width, rect.height);

        const margin = 2;
        const width = rect.width - margin * 2;
        const height = rect.height - margin * 2;
        const min = Math.min(...data);
        const max = Math.max(...data);
        const range = max - min || 1;

        // Draw line
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        ctx.beginPath();
        data.forEach((value, index) => {
            const x = margin + (index / (data.length - 1)) * width;
            const y = margin + ((max - value) / range) * height;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();

        // Draw fill gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, rect.height);
        gradient.addColorStop(0, color + '30');
        gradient.addColorStop(1, color + '05');
        
        ctx.fillStyle = gradient;
        ctx.lineTo(margin + width, margin + height);
        ctx.lineTo(margin, margin + height);
        ctx.closePath();
        ctx.fill();
    },

    getSparklineColor(key) {
        const colors = {
            tenants: '#10B981',    // green  
            users: '#3B82F6',      // blue
            errors: '#EF4444',     // red
            queue: '#F59E0B',      // amber
            storage: '#8B5CF6'     // violet
        };
        return colors[key] || '#6B7280';
    },

    async exportChart(type, range = '30') {
        try {
            const url = `/api/admin/dashboard/${type}/export.csv?range=${range}d`;
            const response = await fetch(url, {
                headers: { 'Accept': 'text/csv' }
            });

            if (!response.ok) {
                if (response.status === 429) {
                    const retryAfter = response.headers.get('Retry-After');
                    throw new Error(`Rate limited. Please try again in ${retryAfter} seconds.`);
                }
                throw new Error(`Export failed: ${response.statusText}`);
            }

            const blob = await response.blob();
            const url_obj = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url_obj;
            a.download = `${type}-export-${range}d.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url_obj);
            
        } catch (error) {
            this.showError('Export failed: ' + error.message);
        }
    },

    showError(message) {
        console.error(message);
        
        // Show user-friendly error toast
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 5000);
    },

    exportSignups: function(range) { this.exportChart('signups', range); },
    exportErrors: function(range) { this.exportChart('errors', range); }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname === '/admin') {
        // Wait for Alpine.js to initialize
        setTimeout(() => window.dashboard.refresh(), 100);
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    state.charts?.destroy();
    if (state.aborter) state.aborter.abort();
});

console.log('🎯 Dashboard module loaded with SWR/ETag support');
