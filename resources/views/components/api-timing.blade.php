<div class="api-timing-display">
    <div class="api-timing-header">
        <h3>API Timing Monitor</h3>
        <button class="refresh-btn" onclick="refreshApiTiming()">Refresh</button>
    </div>
    
    <div class="api-timing-metrics">
        <div class="metric-item">
            <div class="metric-label">Average Response Time</div>
            <div class="metric-value" id="avg-response-time">-</div>
            <div class="metric-unit">ms</div>
        </div>
        
        <div class="metric-item">
            <div class="metric-label">Peak Response Time</div>
            <div class="metric-value" id="peak-response-time">-</div>
            <div class="metric-unit">ms</div>
        </div>
        
        <div class="metric-item">
            <div class="metric-label">P95 Response Time</div>
            <div class="metric-value" id="p95-response-time">-</div>
            <div class="metric-unit">ms</div>
        </div>
        
        <div class="metric-item">
            <div class="metric-label">API Status</div>
            <div class="metric-value" id="api-status">-</div>
            <div class="metric-unit">
                <span class="status-indicator" id="api-status-dot"></span>
            </div>
        </div>
    </div>
    
    <div class="api-endpoints">
        <h4>Endpoint Performance</h4>
        <div id="api-endpoints-list">
            <div class="loading">Loading endpoints...</div>
        </div>
    </div>
    
    <div class="api-timing-chart">
        <canvas id="api-timing-chart" width="400" height="200"></canvas>
    </div>
</div>

<style>
.api-timing-display {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.api-timing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.api-timing-header h3 {
    margin: 0;
    color: #333;
}

.refresh-btn {
    background-color: #2563eb;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.refresh-btn:hover {
    background-color: #1d4ed8;
}

.api-timing-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.metric-item {
    text-align: center;
    padding: 15px;
    background-color: #f8fafc;
    border-radius: 6px;
}

.metric-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 18px;
    font-weight: 600;
    color: #2563eb;
    margin-bottom: 4px;
}

.metric-unit {
    font-size: 12px;
    color: #666;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #ccc;
}

.status-indicator.healthy { background-color: #10b981; }
.status-indicator.warning { background-color: #f59e0b; }
.status-indicator.critical { background-color: #ef4444; }

.api-endpoints {
    margin: 20px 0;
}

.api-endpoints h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.endpoint-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.endpoint-item:last-child {
    border-bottom: none;
}

.endpoint-name {
    font-weight: 500;
    color: #333;
}

.endpoint-time {
    font-weight: 600;
    color: #2563eb;
}

.endpoint-status {
    display: flex;
    align-items: center;
    gap: 8px;
}

.api-timing-chart {
    margin: 20px 0;
    text-align: center;
}

.loading {
    text-align: center;
    color: #666;
    padding: 20px;
}
</style>

<script>
class ApiTimingDisplay {
    constructor() {
        this.apiTimes = [];
        this.chart = null;
        this.init();
    }

    init() {
        this.loadApiTimingData();
        this.initChart();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            this.loadApiTimingData();
        }, 30000);
    }

    async loadApiTimingData() {
        try {
            const response = await fetch('/api/admin/performance/stats', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data.api_response_time) {
                    this.updateApiTimingMetrics(data.data.api_response_time);
                    this.updateApiEndpoints(data.data.api_response_time);
                }
            }
        } catch (error) {
            console.error('Failed to load API timing data:', error);
        }
    }

    updateApiTimingMetrics(metrics) {
        if (metrics.avg) {
            document.getElementById('avg-response-time').textContent = metrics.avg.toFixed(2);
        }
        if (metrics.max) {
            document.getElementById('peak-response-time').textContent = metrics.max.toFixed(2);
        }
        if (metrics.p95) {
            document.getElementById('p95-response-time').textContent = metrics.p95.toFixed(2);
        }

        // Update API status
        const avgTime = metrics.avg || 0;
        this.updateApiStatus(avgTime);
    }

    updateApiStatus(avgTime) {
        const statusElement = document.getElementById('api-status');
        const dotElement = document.getElementById('api-status-dot');
        
        let status, statusClass;
        if (avgTime < 300) {
            status = 'Good';
            statusClass = 'healthy';
        } else if (avgTime < 600) {
            status = 'Slow';
            statusClass = 'warning';
        } else {
            status = 'Critical';
            statusClass = 'critical';
        }

        statusElement.textContent = status;
        dotElement.className = `status-indicator ${statusClass}`;
    }

    updateApiEndpoints(metrics) {
        const container = document.getElementById('api-endpoints-list');
        
        if (!metrics.endpoints || metrics.endpoints.length === 0) {
            container.innerHTML = '<div class="loading">No endpoint data available</div>';
            return;
        }

        const html = metrics.endpoints.map(endpoint => `
            <div class="endpoint-item">
                <span class="endpoint-name">${endpoint.endpoint}</span>
                <div class="endpoint-status">
                    <span class="endpoint-time">${endpoint.response_time.toFixed(2)}ms</span>
                    <span class="status-indicator ${endpoint.response_time < 300 ? 'healthy' : (endpoint.response_time < 600 ? 'warning' : 'critical')}"></span>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    initChart() {
        const canvas = document.getElementById('api-timing-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        this.chart = {
            canvas: canvas,
            ctx: ctx,
            data: []
        };
    }

    updateChart() {
        if (!this.chart || this.apiTimes.length < 2) return;

        const ctx = this.chart.ctx;
        const canvas = this.chart.canvas;
        const width = canvas.width;
        const height = canvas.height;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        // Draw chart
        const data = this.apiTimes.map(item => item.time);
        const maxValue = Math.max(...data);
        const minValue = Math.min(...data);
        const range = maxValue - minValue || 1;

        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 2;
        ctx.beginPath();

        data.forEach((value, index) => {
            const x = (index / (data.length - 1)) * width;
            const y = height - ((value - minValue) / range) * height;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });

        ctx.stroke();

        // Draw threshold line
        ctx.strokeStyle = '#f59e0b';
        ctx.lineWidth = 1;
        ctx.setLineDash([5, 5]);
        ctx.beginPath();
        const thresholdY = height - ((300 - minValue) / range) * height;
        ctx.moveTo(0, thresholdY);
        ctx.lineTo(width, thresholdY);
        ctx.stroke();
        ctx.setLineDash([]);
    }

    recordApiTime(endpoint, responseTime) {
        this.apiTimes.push({
            endpoint: endpoint,
            time: responseTime,
            timestamp: new Date()
        });

        // Keep only last 20 measurements
        if (this.apiTimes.length > 20) {
            this.apiTimes = this.apiTimes.slice(-20);
        }

        this.updateChart();
    }
}

// Global function for refresh button
function refreshApiTiming() {
    if (window.apiTimingDisplay) {
        window.apiTimingDisplay.loadApiTimingData();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.apiTimingDisplay = new ApiTimingDisplay();
});

// Intercept fetch requests to measure API timing
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const startTime = performance.now();
    return originalFetch.apply(this, args).then(response => {
        const endTime = performance.now();
        const responseTime = endTime - startTime;
        
        // Record API timing
        if (window.apiTimingDisplay && args[0]) {
            const url = typeof args[0] === 'string' ? args[0] : args[0].url;
            window.apiTimingDisplay.recordApiTime(url, responseTime);
        }
        
        return response;
    });
};
</script>
