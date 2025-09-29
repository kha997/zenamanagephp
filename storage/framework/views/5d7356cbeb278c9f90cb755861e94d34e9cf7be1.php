
<div class="bg-white shadow-md rounded-lg p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-3">MFA Adoption</h4>
            <div class="chart-box relative h-64">
                <canvas 
                    id="mfa-adoption-chart" 
                    class="security-chart"
                    aria-label="MFA adoption percentage over time"
                    x-ref="mfaChart"
                ></canvas>
            </div>
        </div>

        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Login Attempts</h4>
            <div class="chart-box relative h-64">
                <canvas 
                    id="login-attempts-chart" 
                    class="security-chart"
                    aria-label="Login attempts success vs failed over time"
                    x-ref="loginChart"
                ></canvas>
            </div>
        </div>

        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Active Sessions</h4>
            <div class="chart-box relative h-64">
                <canvas 
                    id="active-sessions-chart" 
                    class="security-chart"
                    aria-label="Active sessions count over time"
                    x-ref="sessionsChart"
                ></canvas>
            </div>
        </div>

        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-3">Failed Logins</h4>
            <div class="chart-box relative h-64">
                <canvas 
                    id="failed-logins-chart" 
                    class="security-chart"
                    aria-label="Failed login attempts over time"
                    x-ref="failedChart"
                ></canvas>
            </div>
        </div>
    </div>

    
    <div x-show="chartError" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
            <span class="text-red-700" x-text="chartError"></span>
            <button 
                @click="loadCharts()" 
                class="ml-auto px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700"
            >
                Retry
            </button>
        </div>
    </div>
</div>

<script>
// Chart.js configuration and data loading
document.addEventListener('alpine:init', () => {
    Alpine.data('securityCharts', () => ({
        period: '30d',
        chartError: null,
        abortController: null,
        
        init() {
            // Use requestIdleCallback for non-critical initial chart rendering
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => {
                    this.loadCharts();
                }, { timeout: 700 });
            } else {
                // Fallback for browsers without requestIdleCallback
                setTimeout(() => {
                    this.loadCharts();
                }, 100);
            }
        },
        
        async changePeriod(newPeriod) {
            if (this.period === newPeriod) return;
            
            this.period = newPeriod;
            this.loadCharts();
        },
        
        async loadCharts() {
            // Cancel previous request
            if (this.abortController) {
                this.abortController.abort();
            }
            
            this.abortController = new AbortController();
            this.chartError = null;
            
            try {
                const response = await fetch(`/api/admin/security/kpis-bypass?period=${this.period}`, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    signal: this.abortController.signal
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                this.renderCharts(data.data);
                
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Chart loading error:', error);
                    this.chartError = 'Failed to load chart data. Please try again.';
                    this.showChartErrors();
                }
            }
        },
        
        renderCharts(data) {
            try {
                console.log('Rendering charts with data:', data);
                
                // Use requestAnimationFrame for smooth updates
                requestAnimationFrame(() => {
                    // MFA Adoption Chart
                    if (data.mfaAdoption && data.mfaAdoption.series) {
                        const mfaData = this.buildChartData(data.mfaAdoption.series, 'MFA Adoption %');
                        if (this.$refs.mfaChart) {
                            this.renderSimpleChart(this.$refs.mfaChart, mfaData, 'line');
                        }
                    }
                    
                    // Login Attempts Chart (stacked)
                    if (data.loginAttempts) {
                        const loginData = this.buildStackedChartData(
                            data.loginAttempts.success || [],
                            data.loginAttempts.failed || []
                        );
                        if (this.$refs.loginChart) {
                            this.renderSimpleChart(this.$refs.loginChart, loginData, 'bar');
                        }
                    }
                    
                    // Active Sessions Chart
                    if (data.activeSessions && data.activeSessions.series) {
                        const sessionsData = this.buildChartData(data.activeSessions.series, 'Active Sessions');
                        if (this.$refs.sessionsChart) {
                            this.renderSimpleChart(this.$refs.sessionsChart, sessionsData, 'line');
                        }
                    }
                    
                    // Failed Logins Chart
                    if (data.failedLogins && data.failedLogins.series) {
                        const failedData = this.buildChartData(data.failedLogins.series, 'Failed Logins');
                        if (this.$refs.failedChart) {
                            this.renderSimpleChart(this.$refs.failedChart, failedData, 'line');
                        }
                    }
                });
                
            } catch (error) {
                console.error('Chart rendering error:', error);
                this.chartError = 'Failed to render charts. Please try again.';
                this.showChartErrors();
            }
        },
        
        renderSimpleChart(canvas, data, type = 'line') {
            try {
                // Destroy existing chart
                if (canvas._chart) {
                    canvas._chart.destroy();
                }
                
                const ctx = canvas.getContext('2d');
                
                // Simple chart rendering without Chart.js
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                if (!data.labels || data.labels.length === 0) {
                    ctx.fillStyle = '#6B7280';
                    ctx.font = '14px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('No data available', canvas.width / 2, canvas.height / 2);
                    return;
                }
                
                // Draw simple line chart
                const padding = 40;
                const chartWidth = canvas.width - padding * 2;
                const chartHeight = canvas.height - padding * 2;
                
                const dataset = data.datasets[0];
                const values = dataset.data;
                const maxValue = Math.max(...values);
                const minValue = Math.min(...values);
                const valueRange = maxValue - minValue || 1;
                
                // Draw axes
                ctx.strokeStyle = '#E5E7EB';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(padding, padding);
                ctx.lineTo(padding, canvas.height - padding);
                ctx.lineTo(canvas.width - padding, canvas.height - padding);
                ctx.stroke();
                
                // Draw data line
                ctx.strokeStyle = dataset.borderColor || '#3B82F6';
                ctx.lineWidth = 2;
                ctx.beginPath();
                
                values.forEach((value, index) => {
                    const x = padding + (index / (values.length - 1)) * chartWidth;
                    const y = canvas.height - padding - ((value - minValue) / valueRange) * chartHeight;
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                
                ctx.stroke();
                
                // Draw title
                ctx.fillStyle = '#374151';
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(dataset.label || 'Chart', canvas.width / 2, 20);
                
            } catch (error) {
                console.error('Simple chart rendering error:', error);
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#6B7280';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Chart rendering error', canvas.width / 2, canvas.height / 2);
            }
        },
        
        buildChartData(series, label) {
            const points = series.map((value, index) => ({
                ts: new Date(Date.now() - (series.length - index - 1) * 24 * 60 * 60 * 1000).toISOString(),
                value: value
            }));
            
            const downsampled = this.downsampleData(points);
            
            return {
                labels: downsampled.map(p => p.ts),
                datasets: [{
                    label: label,
                    data: downsampled.map(p => p.value),
                    borderColor: '#3B82F6',
                    backgroundColor: '#3B82F620',
                    tension: 0.1,
                    fill: false
                }]
            };
        },
        
        buildStackedChartData(successPoints, failedPoints) {
            const maxLength = Math.max(successPoints.length, failedPoints.length);
            const labels = [];
            const successData = [];
            const failedData = [];
            
            for (let i = 0; i < maxLength; i++) {
                const timestamp = new Date(Date.now() - (maxLength - i - 1) * 24 * 60 * 60 * 1000).toISOString();
                labels.push(timestamp);
                successData.push(successPoints[i] || 0);
                failedData.push(failedPoints[i] || 0);
            }
            
            return {
                labels,
                datasets: [
                    {
                        label: 'Successful Logins',
                        data: successData,
                        backgroundColor: '#10B981',
                        borderColor: '#10B981'
                    },
                    {
                        label: 'Failed Logins',
                        data: failedData,
                        backgroundColor: '#EF4444',
                        borderColor: '#EF4444'
                    }
                ]
            };
        },
        
        downsampleData(points, maxPoints = 365) {
            if (points.length <= maxPoints) {
                return points;
            }
            
            const step = Math.ceil(points.length / maxPoints);
            const downsampled = [];
            
            for (let i = 0; i < points.length; i += step) {
                const chunk = points.slice(i, i + step);
                const avgValue = chunk.reduce((sum, p) => sum + p.value, 0) / chunk.length;
                const timestamp = chunk[0].ts;
                
                downsampled.push({
                    ts: timestamp,
                    value: Math.round(avgValue * 100) / 100
                });
            }
            
            return downsampled;
        },
        
        showChartErrors() {
            // Show error state for all charts
            [this.$refs.mfaChart, this.$refs.loginChart, this.$refs.sessionsChart, this.$refs.failedChart]
                .filter(canvas => canvas)
                .forEach(canvas => {
                    if (canvas._chart) {
                        canvas._chart.destroy();
                    }
                    
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    ctx.fillStyle = '#6B7280';
                    ctx.font = '14px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('Error loading data', canvas.width / 2, canvas.height / 2);
                });
        }
    }));
});
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_charts.blade.php ENDPATH**/ ?>