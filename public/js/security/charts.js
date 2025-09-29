// Security Charts Module - Standardized with ETag/SWR support
class SecurityChartsManager {
    constructor(containerEl, kpisApi) {
        this.containerEl = containerEl;
        this.kpisApi = kpisApi;
        this.charts = {};
        this.abortController = null;
        this.currentPeriod = '30d';
        this.retryCount = 0;
        this.maxRetries = 3;
        this.chartData = {};
        
        this.init();
    }

    init() {
        console.log('SecurityChartsManager initialized');
        this.setupEventListeners();
        this.loadChartData();
    }

    setupEventListeners() {
        // Period selector buttons
        this.containerEl.querySelectorAll('[data-period]').forEach(btn => {
            btn.addEventListener('click', () => {
                const period = btn.dataset.period;
                if (period !== this.currentPeriod) {
                    this.changePeriod(period);
                }
            });
        });

        // Lifecycle management
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.cleanup();
            } else {
                this.reinit();
            }
        });

        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }

    async loadChartData(retryCount = 0) {
        try {
            // Cancel previous request
            if (this.abortController) {
                this.abortController.abort();
            }
            
            this.abortController = new AbortController();
            
            // Use standard /kpis endpoint with ETag support
            const response = await fetch(`/api/admin/security/kpis-bypass?period=${this.currentPeriod}`, {
                headers: { 
                    'Accept': 'application/json',
                    'If-None-Match': this.lastETag || undefined
                },
                signal: this.abortController.signal
            });
            
            if (response.status === 304) {
                console.log('Charts: data unchanged (304)');
                this.renderCharts();
                return;
            }
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            this.lastETag = response.headers.get('ETag');
            this.chartData = this.extractChartData(data.data);
            
            console.log('Chart data loaded:', this.chartData);
            this.renderCharts();
            this.retryCount = 0; // Reset retry on success
            
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Chart request cancelled');
                return;
            }
            
            console.error('Chart loading error:', error);
            this.handleRetry(error, retryCount);
        }
    }

    extractChartData(kpisData) {
        // Transform KPI data to chart series format
        return {
            mfaAdoption: this.createTimeSeries(kpisData?.mfaAdoption?.series || []),
            successfulLogins: this.createTimeSeries(kpisData?.loginAttempts?.successSeries || []),
            activeSessions: this.createTimeSeries(kpisData?.activeSessions?.series || []),
            failedLogins: this.createTimeSeries(kpisData?.loginAttempts?.failedSeries || [])
        };
    }

    createTimeSeries(series) {
        return series.map(point => ({
            date: point.ts || point.date,
            value: point.value
        })).sort((a, b) => new Date(a.date) - new Date(b.date));
    }

    renderCharts() {
        try {
            // Destroy existing charts
            this.destroyCharts();
            
            // Respect reduced motion preference
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            
            // Base Chart.js configuration
            const baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: prefersReducedMotion ? 0 : 220 },
                interaction: { mode: 'index', intersect: false },
                elements: { 
                    point: { radius: 0 }, 
                    line: { borderWidth: 2, tension: 0.2 } 
                },
                plugins: {
                    legend: { display: false },
                    decimation: { 
                        enabled: true, 
                        algorithm: 'lttb', 
                        threshold: 365 
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => this.formatTooltip(context)
                        }
                    }
                },
                scales: {
                    x: { 
                        type: 'time', 
                        time: { 
                            unit: this.getTimeUnit()
                        },
                        grid: { display: false },
                        ticks: {
                            source: 'auto',
                            maxRotation: 0,
                            callback: this.formatXAxisLabel.bind(this)
                        }
                    },
                    y: { 
                        beginAtZero: true,
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            };

            // Create MFA Adoption Chart (Blue)
            this.createChart('mfa-adoption-chart', this.chartData.mfaAdoption, {
                ...baseOptions,
                datasets: [{
                    borderColor: '#3B82F6',
                    backgroundColor: '#3B82F622',
                    tension: 0.2,
                    fill: false
                }]
            }, 'percentage');

            // Create Successful Logins Chart (Green)
            this.createChart('successful-logins-chart', this.chartData.successfulLogins, {
                ...baseOptions,
                datasets: [{
                    borderColor: '#10B981',
                    backgroundColor: '#10B98122',
                    tension: 0.2,
                    fill: false
                }]
            }, 'count');

            // Create Active Sessions Chart (Indigo with fill)
            this.createChart('active-sessions-chart', this.chartData.activeSessions, {
                ...baseOptions,
                datasets: [{
                    borderColor: '#6366F1',
                    backgroundColor: '#6366F122',
                    tension: 0.2,
                    fill: true
                }]
            }, 'count');

            // Create Failed Logins Chart (Red)
            this.createChart('failed-logins-chart', this.chartData.failedLogins, {
                ...baseOptions,
                datasets: [{
                    borderColor: '#EF4444',
                    backgroundColor: '#EF444422',
                    tension: 0.2,
                    fill: false
                }]
            }, 'count');

            console.log('Charts rendered successfully');
            
        } catch (error) {
            console.error('Chart rendering error:', error);
            this.showError('Failed to render charts. Please try again.');
        }
    }

    createChart(chartId, data, options, valueType) {
        const canvas = document.getElementById(chartId);
        if (!canvas) {
            console.warn(`Canvas ${chartId} not found`);
            return;
        }

        if (!data || !data.length) {
            console.warn(`No data for chart ${chartId}`);
            return;
        }

        try {
            // Format y-axis for MFA percentage
            const chartOptions = { ...options };
 with proper label callbacks
            if (valueType === 'percentage') {
                chartOptions.scales.y.ticks = {
                    ...chartOptions.scales.y.ticks,
                    callback: (value) => `${value}%`
                };
            } else {
                chartOptions.scales.y.ticks = {
                    ...chartOptions.scales.y.ticks,
                    callback: (value) => value.toLocaleString()
                };
            }

            this.charts[chartId] = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.map(d => d.date),
                    datasets: chartOptions.datasets.map(dataset => ({
                        ...dataset,
                        data: data.map(d => d.value)
                    }))
                },
                options: chartOptions
            });

            // Add accessibility
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', `${chartId.replace('-chart', '')} security trend`);
            
        } catch (error) {
            console.error(`Error creating chart ${chartId}:`, error);
        }
    }

    getTimeUnit() {
        switch (this.currentPeriod) {
            case '7d': return 'day';
            case '30d': return 'day';
            case '90d': return 'week';
            default: return 'day';
        }
    }

    formatTooltip(context) {
        const date = new Date(context.label).toLocaleDateString('en-GB');
        const value = context.parsed.y;
        const chartId = context.chart.canvas.id;
        
        if (chartId === 'mfa-adoption-chart') {
            return `${date}: ${value}%`;
        }
        return `${date}: ${value.toLocaleString()}`;
    }

    formatXAxisLabel(value, index, values) {
        if (values.length > 7) {
            // Only show labels for first, middle, and last for readability
            if (index === 0 || index === Math.floor(values.length / 2) || index === values.length - 1) {
                return new Date(value).toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
            }
            return '';
        }
        return new Date(value).toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
    }

    changePeriod(newPeriod) {
        if (newPeriod !== this.currentPeriod) {
            this.currentPeriod = newPeriod;
            this.loadChartData();
        }
    }

    async handleRetry(error, retryCount) {
        if (retryCount >= this.maxRetries) {
            this.showError('Cannot load chart data after multiple attempts. Please refresh the page.');
            return;
        }

        const delay = Math.pow(2, retryCount) * 1000; // Exponential backoff
        console.log(`Retrying chart load in ${delay}ms (attempt ${retryCount + 1})`);
        
        setTimeout(() => {
            this.loadChartData(retryCount + 1);
        }, delay);
    }

    showError(message) {
        // Hide charts and show error message
        Object.keys(this.charts).forEach(chartId => {
            const canvas = document.getElementById(chartId);
            if (canvas) {
                canvas.style.display = 'none';
            }
        });

        // Create or update error element
        let errorEl = this.containerEl.querySelector('.chart-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'chart-error text-center p-6 text-red-500';
            this.containerEl.appendChild(errorEl);
        }
        
        errorEl.innerHTML = `
            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
            <p class="text-sm">${message}</p>
            <button onclick="window.location.reload()" class="mt-2 px-3 py-1 bg-red-100 text-red-700 rounded text-sm">
                Retry
            </button>
        `;
    }

    destroyCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart && chart.destroy) {
                chart.destroy();
            }
        });
        this.charts = {};
    }

    cleanup() {
        this.destroyCharts();
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    reinit() {
        if (Object.keys(this.charts).length === 0) {
            this.loadChartData();
        }
    }
}

// Export for global access
window.SecurityChartsManager = SecurityChartsManager;