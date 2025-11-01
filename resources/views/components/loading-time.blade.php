<div class="loading-time-display">
    <div class="loading-time-header">
        <h3>Loading Time Monitor</h3>
        <button class="refresh-btn" onclick="refreshLoadingTime()">Refresh</button>
    </div>
    
    <div class="loading-time-metrics">
        <div class="metric-item">
            <div class="metric-label">Current Page Load</div>
            <div class="metric-value" id="current-load-time">-</div>
            <div class="metric-unit">ms</div>
        </div>
        
        <div class="metric-item">
            <div class="metric-label">Average Load Time</div>
            <div class="metric-value" id="avg-load-time">-</div>
            <div class="metric-unit">ms</div>
        </div>
        
        <div class="metric-item">
            <div class="metric-label">Peak Load Time</div>
            <div class="metric-value" id="peak-load-time">-</div>
            <div class="metric-unit">ms</div>
        </div>
        
        <div class="metric-item">
            <div class="metric-label">Load Time Status</div>
            <div class="metric-value" id="load-time-status">-</div>
            <div class="metric-unit">
                <span class="status-indicator" id="load-status-dot"></span>
            </div>
        </div>
    </div>
    
    <div class="loading-time-chart">
        <canvas id="loading-time-chart" width="400" height="200"></canvas>
    </div>
    
    <div class="loading-time-history">
        <h4>Recent Load Times</h4>
        <div id="loading-time-history-list">
            <div class="loading">Loading history...</div>
        </div>
    </div>
</div>

<style>
.loading-time-display {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.loading-time-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.loading-time-header h3 {
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

.loading-time-metrics {
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

.loading-time-chart {
    margin: 20px 0;
    text-align: center;
}

.loading-time-history h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.loading-time-history-list {
    max-height: 200px;
    overflow-y: auto;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.history-item:last-child {
    border-bottom: none;
}

.history-time {
    color: #666;
}

.history-value {
    font-weight: 600;
    color: #2563eb;
}

.loading {
    text-align: center;
    color: #666;
    padding: 20px;
}
</style>

<script>
class LoadingTimeDisplay {
    constructor() {
        this.loadTimes = [];
        this.chart = null;
        this.init();
    }

    init() {
        this.loadCurrentPageLoadTime();
        this.loadLoadingTimeHistory();
        this.initChart();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            this.loadLoadingTimeHistory();
        }, 30000);
    }

    loadCurrentPageLoadTime() {
        const loadTime = performance.now();
        document.getElementById('current-load-time').textContent = loadTime.toFixed(2);
        
        // Record this load time
        this.recordLoadTime(loadTime);
        
        // Update status
        this.updateLoadTimeStatus(loadTime);
    }

    async loadLoadingTimeHistory() {
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
                if (data.success && data.data.page_load_time) {
                    this.updateLoadingTimeMetrics(data.data.page_load_time);
                    this.updateLoadingTimeHistory(data.data.page_load_time);
                }
            }
        } catch (error) {
            console.error('Failed to load loading time history:', error);
        }
    }

    updateLoadingTimeMetrics(metrics) {
        if (metrics.avg) {
            document.getElementById('avg-load-time').textContent = metrics.avg.toFixed(2);
        }
        if (metrics.max) {
            document.getElementById('peak-load-time').textContent = metrics.max.toFixed(2);
        }
    }

    updateLoadingTimeHistory(metrics) {
        const container = document.getElementById('loading-time-history-list');
        
        if (!metrics.history || metrics.history.length === 0) {
            container.innerHTML = '<div class="loading">No history available</div>';
            return;
        }

        const html = metrics.history.slice(-10).reverse().map(item => `
            <div class="history-item">
                <span class="history-time">${new Date(item.timestamp).toLocaleTimeString()}</span>
                <span class="history-value">${item.load_time.toFixed(2)}ms</span>
            </div>
        `).join('');

        container.innerHTML = html;
    }

    recordLoadTime(loadTime) {
        this.loadTimes.push({
            time: loadTime,
            timestamp: new Date()
        });

        // Keep only last 20 measurements
        if (this.loadTimes.length > 20) {
            this.loadTimes = this.loadTimes.slice(-20);
        }

        this.updateChart();
    }

    updateLoadTimeStatus(loadTime) {
        const statusElement = document.getElementById('load-time-status');
        const dotElement = document.getElementById('load-status-dot');
        
        let status, statusClass;
        if (loadTime < 500) {
            status = 'Good';
            statusClass = 'healthy';
        } else if (loadTime < 1000) {
            status = 'Slow';
            statusClass = 'warning';
        } else {
            status = 'Critical';
            statusClass = 'critical';
        }

        statusElement.textContent = status;
        dotElement.className = `status-indicator ${statusClass}`;
    }

    initChart() {
        const canvas = document.getElementById('loading-time-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        this.chart = {
            canvas: canvas,
            ctx: ctx,
            data: []
        };
    }

    updateChart() {
        if (!this.chart || this.loadTimes.length < 2) return;

        const ctx = this.chart.ctx;
        const canvas = this.chart.canvas;
        const width = canvas.width;
        const height = canvas.height;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        // Draw chart
        const data = this.loadTimes.map(item => item.time);
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
        const thresholdY = height - ((500 - minValue) / range) * height;
        ctx.moveTo(0, thresholdY);
        ctx.lineTo(width, thresholdY);
        ctx.stroke();
        ctx.setLineDash([]);
    }
}

// Global function for refresh button
function refreshLoadingTime() {
    if (window.loadingTimeDisplay) {
        window.loadingTimeDisplay.loadCurrentPageLoadTime();
        window.loadingTimeDisplay.loadLoadingTimeHistory();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.loadingTimeDisplay = new LoadingTimeDisplay();
});
</script>
