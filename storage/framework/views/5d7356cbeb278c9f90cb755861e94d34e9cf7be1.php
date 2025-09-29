
<div class="bg-white shadow-md rounded-lg mb-6">
    <div class="flex justify-between items-center p-6 pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Security Trends</h3>
        <div class="flex space-x-2">
            <button 
                @click="changePeriod('7d')" 
                :class="{'bg-indigo-600 text-white': period === '7d', 'bg-gray-200 text-gray-700': period !== '7d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
                aria-label="7 days period"
            >
                7d
            </button>
            <button 
                @click="changePeriod('30d')" 
                :class="{'bg-indigo-600 text-white': period === '30d', 'bg-gray-200 text-gray-700': period !== '30d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
                aria-label="30 days period"
            >
                30d
            </button>
            <button 
                @click="changePeriod('90d')" 
                :class="{'bg-indigo-600 text-white': period === '90d', 'bg-gray-200 text-gray-700': period !== '90d'}"
                class="px-3 py-1 rounded text-sm font-medium transition-colors"
                aria-label="90 days period"
            >
                90d
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6 pt-0">
        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">MFA Adoption</h4>
                <p class="text-xs text-gray-500 mb-4">MFA Adoption %</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas 
                        id="mfa-adoption-chart" 
                        class="security-chart"
                        aria-label="MFA adoption percentage over time"
                        x-ref="mfaChart"
                        x-show="!loading"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.mfaAdoption?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Login Attempts</h4>
                <p class="text-xs text-gray-500 mb-4">Successful Logins</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas 
                        id="successful-logins-chart" 
                        class="security-chart"
                        aria-label="Successful login attempts over time"
                        x-ref="successfulChart"
                        x-show="!loading"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.successfulLogins?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Active Sessions</h4>
                <p class="text-xs text-gray-500 mb-4">Active Sessions</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas 
                        id="active-sessions-chart" 
                        class="security-chart"
                        aria-label="Active sessions count over time"
                        x-ref="sessionsChart"
                        x-show="!loading"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.activeSessions?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="chart-card bg-white border border-gray-100 rounded-lg">
            <div class="p-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Failed Logins</h4>
                <p class="text-xs text-gray-500 mb-4">Failed Logins</p>
            </div>
            <div class="chart-wrap flex-1 px-4 pb-4">
                <div class="chart-box relative" style="height: 220px;">
                    <canvas 
                        id="failed-logins-chart" 
                        class="security-chart"
                        aria-label="Failed login attempts over time"
                        x-ref="failedChart"
                        x-show="!loading"
                    ></canvas>
                    <!-- Skeleton -->
                    <div x-show="loading" class="absolute inset-0 bg-gray-100 animate-pulse rounded"></div>
                    <!-- Empty State -->
                    <div x-show="!loading && !chartData?.failedLogins?.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
                        <i class="fas fa-chart-line text-3xl mb-2"></i>
                        <p class="text-sm">No data in selected period</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('securityCharts', () => ({
        period: '30d',
        loading: true,
        chartError: null,
        chartData: {},
        charts: {},
        
        init() {
            console.log('Security charts component initialized');
            
            // Wait for Chart.js to be available
            const waitForChart = () => {
                if (typeof Chart !== 'undefined') {
                    this.loadCharts();
                } else {
                    setTimeout(waitForChart, 50);
                }
            };
            
            // Use requestIdleCallback for non-critical initial chart rendering
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => {
                    waitForChart();
                }, { timeout: 700 });
            } else {
                setTimeout(() => {
                    waitForChart();
                }, 100);
            }
        },
        
        async loadCharts() {
            this.loading = true;
            this.chartError = null;
            
            try {
                const params = new URLSearchParams();
                params.set('period', this.period);
                
                const response = await fetch(`/api/admin/security/charts-bypass?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                const data = await response.json();
                this.chartData = data.data || {};
                
                console.log('Chart data loaded:', this.chartData);
                
                // Render charts with requestAnimationFrame for smooth updates
                requestAnimationFrame(() => {
                    this.renderCharts();
                });
                
            } catch (error) {
                console.error('Chart loading error:', error);
                this.chartError = error.message;
            } finally {
                this.loading = false;
            }
        },
        
        renderCharts() {
            try {
                console.log('Rendering charts with data:', this.chartData);
                
                // Destroy any existing charts to prevent memory leaks
                Object.values(this.charts).forEach(chart => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });
                this.charts = {};
                
                // Base Chart.js options
                const baseOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 220 },
                    interaction: { mode: 'index', intersect: false },
                    elements: { 
                        point: { radius: 0 }, 
                        line: { borderWidth: 2, tension: 0.2 } 
                    },
                    plugins: {
                        legend: { display: false },
                        decimation: { enabled: true, algorithm: 'lttb', threshold: 365 },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const date = new Date(context.label).toLocaleDateString('en-GB');
                                    const value = context.parsed.y;
                                    return `${date}: ${value}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            type: 'time', 
                            time: { 
                                unit: this.period === '7d' ? 'day' : this.period === '90d' ? 'week' : 'day'
                            },
                            grid: { display: false },
                            ticks: {
                                source: 'auto',
                                maxRotation: 0,
                                callback: function(value, index, values) {
                                    const date = new Date(value);
                                    return date.toLocaleDateString('en-GB');
                                }
                            }
                        },
                        y: { 
                            beginAtZero: true,
                            grid: { display: false },
                            ticks: { precision: 0 },
                            border: { display: false }
                        }
                    }
                };
                
                // MFA Adoption Chart (Blue)
                console.log('MFA Chart data:', this.chartData.mfaAdoption);
                console.log('MFA Chart ref:', this.$refs.mfaChart);
                if (this.chartData.mfaAdoption && this.$refs.mfaChart) {
                    const chartConfig = {
                        ...baseOptions,
                        scales: {
                            ...baseOptions.scales,
                            y: {
                                ...baseOptions.scales.y,
                                ticks: {
                                    ...baseOptions.scales.y.ticks,
                                    callback: (value) => `${value}%`
                                }
                            }
                        }
                    };
                    
                    this.charts.mfaAdoption = new Chart(this.$refs.mfaChart, {
                        type: 'line',
                        data: {
                            labels: this.chartData.mfaAdoption.map(d => d.date),
                            datasets: [{
                                data: this.chartData.mfaAdoption.map(d => d.value),
                                borderColor: '#3B82F6',
                                backgroundColor: '#3B82F622',
                                tension: 0.2,
                                fill: false
                            }]
                        },
                        options: chartConfig
                    });
                }
                
                // Successful Logins Chart (Green)
                if (this.chartData.successfulLogins && this.$refs.successfulChart) {
                    this.charts.successfulLogins = new Chart(this.$refs.successfulChart, {
                        type: 'line',
                        data: {
                            labels: this.chartData.successfulLogins.map(d => d.date),
                            datasets: [{
                                data: this.chartData.successfulLogins.map(d => d.value),
                                borderColor: '#10B981',
                                backgroundColor: '#10B98122',
                                tension: 0.2,
                                fill: false
                            }]
                        },
                        options: baseOptions
                    });
                }
                
                // Active Sessions Chart (Indigo with fill)
                if (this.chartData.activeSessions && this.$refs.sessionsChart) {
                    this.charts.activeSessions = new Chart(this.$refs.sessionsChart, {
                        type: 'line',
                        data: {
                            labels: this.chartData.activeSessions.map(d => d.date),
                            datasets: [{
                                data: this.chartData.activeSessions.map(d => d.value),
                                borderColor: '#6366F1',
                                backgroundColor: '#6366F122',
                                tension: 0.2,
                                fill: true
                            }]
                        },
                        options: baseOptions
                    });
                }
                
                // Failed Logins Chart (Red)
                if (this.chartData.failedLogins && this.$refs.failedChart) {
                    this.charts.failedLogins = new Chart(this.$refs.failedChart, {
                        type: 'line',
                        data: {
                            labels: this.chartData.failedLogins.map(d => d.date),
                            datasets: [{
                                data: this.chartData.failedLogins.map(d => d.value),
                                borderColor: '#EF4444',
                                backgroundColor: '#EF444422',
                                tension: 0.2,
                                fill: false
                            }]
                        },
                        options: baseOptions
                    });
                }
                
            } catch (error) {
                console.error('Chart rendering error:', error);
                this.chartError = 'Failed to render charts. Please try again.';
            }
        },
        
        changePeriod(newPeriod) {
            if (newPeriod !== this.period) {
                this.period = newPeriod;
                this.loadCharts();
            }
        }
    }));
});
</script><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_charts.blade.php ENDPATH**/ ?>