// Security Charts Manager - Clean Implementation
class SecurityCharts {
    constructor() {
        this.charts = {};
        this.chartData = {};
        this.chartPeriod = '30d';
        this.loading = false;
        this.error = null;
        
        // Bind methods
        this.changePeriod = this.changePeriod.bind(this);
        this.init = this.init.bind(this);
        
        this.init();
    }

    init() {
        console.log('SecurityCharts: Initializing...');
        
        // Wait for Chart.js to be available
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            this.error = 'Chart.js not available';
            return;
        }

        this.setupEventListeners();
        this.loadChartData();
    }

    setupEventListeners() {
        // Listen for period change events
        document.addEventListener('chartPeriodChanged', (event) => {
            if (event.detail && event.detail.period !== this.chartPeriod) {
                this.chartPeriod = event.detail.period;
                this.loadChartData();
            }
        });

        // Listen for security data updates
        document.addEventListener('security:dataUpdated', (event) => {
            this.chartData = event.detail?.data || {};
            this.renderCharts();
        });
    }

    async loadChartData() {
        if (this.loading) return;
        
        this.loading = true;
        this.error = null;

        try {
            const url = `/api/admin/security/kpis-bypass?period=${this.chartPeriod}`;
            console.log('SecurityCharts: Loading data from', url);

            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            this.chartData = data.data || {};
            
            console.log('SecurityCharts: Data loaded', this.chartData);
            
            // Dispatch data updated event
            document.dispatchEvent(new CustomEvent('security:dataUpdated', {
                detail: { data: this.chartData }
            }));

        } catch (error) {
            console.error('SecurityCharts: Error loading data:', error);
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    }

    renderCharts() {
        if (this.error) {
            console.log('SecurityCharts: Cannot render due to error:', this.error);
            return;
        }

        // Destroy existing charts
        Object.values(this.charts).forEach(chart => {
            if (chart && chart.destroy) {
                chart.destroy();
            }
        });
        this.charts = {};

        // Create charts
        this.createChart('mfa-adoption-chart', this.getChartConfig('MFA Adoption', this.chartData.mfaAdoption?.series, '#3B82F6'));
        this.createChart('login-attempts-chart', this.getChartConfig('Login Attempts', this.chartData.loginAttempts?.success, '#10B981'));
        this.createChart('active-sessions-chart', this.getChartConfig('Active Sessions', this.chartData.activeSessions?.series, '#6366F1'));
        this.createChart('failed-logins-chart', this.getChartConfig('Failed Logins', this.chartData.loginAttempts?.failed, '#EF4444'));

        console.log('SecurityCharts: Charts rendered successfully');
    }

    getChartConfig(title, data, color) {
        if (!data || !Array.isArray(data) || data.length === 0) {
            return null;
        }

        const labels = data.map((_, index) => `Day ${index + 1}`);
        
        return {
        type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: title,
                    data: data,
                    borderColor: color,
                    backgroundColor: `${color}20`,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { 
                mode: 'index', 
                intersect: false 
            },
            plugins: { 
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y}`;
                            }
                        }
                    }
            },
            scales: { 
                x: { 
                        display: false,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        display: false,
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 300
                }
            }
        };
    }

    createChart(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !config) {
            console.log(`SecurityCharts: Skipping chart ${canvasId} - no canvas or config`);
            return;
        }

        try {
            this.charts[canvasId] = new Chart(canvas, config);
            console.log(`SecurityCharts: Created chart ${canvasId}`);
        } catch (error) {
            console.error(`SecurityCharts: Error creating chart ${canvasId}:`, error);
            this.error = `Failed to create ${canvasId}`;
        }
    }

    changePeriod(period) {
        if (period === this.chartPeriod) return;
        
        this.chartPeriod = period;
        
        // Dispatch period change event
        document.dispatchEvent(new CustomEvent('chartPeriodChanged', {
            detail: { period }
        }));
        
        // Load new data
        this.loadChartData();
    }

    destroy() {
        Object.values(this.charts).forEach(chart => {
            if (chart && chart.destroy) {
                chart.destroy();
            }
        });
        this.charts = {};
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('SecurityCharts: DOM ready, initializing charts...');
    
    // Wait for Chart.js to be available
    const initCharts = () => {
        if (typeof Chart !== 'undefined') {
            window.SecurityCharts = new SecurityCharts();
            console.log('SecurityCharts: Initialized successfully');
        } else {
            console.log('SecurityCharts: Chart.js not ready, retrying...');
            setTimeout(initCharts, 100);
        }
    };
    
    initCharts();
});

// Export for global access
window.SecurityCharts = SecurityCharts;
