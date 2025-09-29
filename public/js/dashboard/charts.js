/**
 * Chart.js Dashboard Module - Class-based Chart Management
 */

class DashboardCharts {
    constructor() {
        this.instances = {};
        this.decimationConfig = {
            enabled: true,
            algorithm: 'lttb',
            samples: 100
        };
    }

    initialize() {
        console.log('[Charts] Initializing dashboard charts...');
        
        // Set up chart event listeners
        this.setupEventListeners();
        
        // Initialize sparklines
        this.initSparklines();
        
        console.log('[Charts] Charts initialized');
    }

    setupEventListeners() {
        // Listen for chart data updates
        document.addEventListener('dashboard:chartsUpdated', (event) => {
            this.updateCharts(event.detail.data);
        });

        // Listen for KPI updates to update sparklines
        document.addEventListener('dashboard:kpisUpdated', (event) => {
            this.updateSparklines(event.detail.data);
        });
    }

    // Main Chart Updates
    updateCharts(data) {
        if (data.signups) {
            this.updateSignupsChart(data.signups);
        }
        if (data.error_rate) {
            this.updateErrorRateChart(data.error_rate);
        }
    }

    updateSignupsChart(data) {
        const ctx = document.getElementById('chart-signups');
        if (!ctx) return;

        if (this.instances.signups) {
            this.instances.signups.destroy();
        }

        this.instances.signups = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels.map(label => new Date(label).toLocaleDateString()),
                datasets: [{
                    label: 'New Signups',
                    data: data.datasets[0].data,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0 // Disable animation for faster updates
                },
                plugins: {
                    legend: { display: false },
                    decimation: this.decimationConfig
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            parser: 'YYYY-MM-DD',
                            displayFormats: {
                                day: 'MMM DD'
                            }
                        }
                    },
                    y: { beginAtZero: true }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    updateErrorRateChart(data) {
        const ctx = document.getElementById('chart-errors');
        if (!ctx) return;

        if (this.instances.errors) {
            this.instances.errors.destroy();
        }

        this.instances.errors = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels.map(label => new Date(label).toLocaleDateString()),
                datasets: [{
                    label: 'Error Rate %',
                    data: data.datasets[0].data,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: '#EF4444',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });
    }

    // Sparklines Management
    initSparklines() {
        const sparklineKeys = ['tenantsSparkline', 'usersSparkline', 'errorsSparkline', 'queueSparkline', 'storageSparkline'];
        sparklineKeys.forEach(key => {
            const canvas = document.getElementById(key);
            if (canvas && !this.instances[key]) {
                this.createSparkline(key);
            }
        });
    }

    createSparkline(key) {
        const canvas = document.getElementById(key);
        if (!canvas) return;

        // Mock data for initialization
        const mockData = Array.from({ length: 30 }, () => Math.floor(Math.random() * 10));
        
        this.instances[key] = new Chart(canvas, {
            type: 'line',
            data: {
                labels: Array(30).fill(''),
                datasets: [{
                    data: mockData,
                    borderColor: this.getSparklineColor(key),
                    backgroundColor: this.getSparklineColor(key, 0.1),
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: { point: { radius: 0 } },
                animation: { duration: 300 }
            }
        });
    }

    updateSparklines(data) {
        if (!data) return;

        const kpiKeys = ['tenants', 'users', 'errors', 'queue', 'storage'];
        kpiKeys.forEach(kpi => {
            const sparklineKey = kpi + 'Sparkline';
            if (this.instances[sparklineKey] && data[kpi]?.sparkline) {
                this.instances[sparklineKey].data.datasets[0].data = data[kpi].sparkline;
                this.instances[sparklineKey].update('none');
            }
        });
    }

    getSparklineColor(key, alpha = 1) {
        const colors = {
            tenantsSparkline: `rgba(16, 185, 129, ${alpha})`, // Green
            usersSparkline: `rgba(16, 185, 129, ${alpha})`,   // Green
            errorsSparkline: `rgba(239, 68, 68, ${alpha})`,  // Red
            queueSparkline: `rgba(245, 158, 11, ${alpha})`,   // Orange
            storageSparkline: `rgba(139, 92, 246, ${alpha})`  // Purple
        };
        return colors[key] || `rgba(107, 114, 128, ${alpha})`;
    }

    // Cleanup
    destroy() {
        Object.values(this.instances).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        this.instances = {};
        console.log('[Charts] Charts destroyed');
    }
}

// Global instance
window.DashboardCharts = new DashboardCharts();
console.log('[Charts] Chart module loaded');