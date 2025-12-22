<div class="performance-indicators">
    <div class="performance-indicator" data-metric="page-load-time">
        <div class="indicator-label">Page Load Time</div>
        <div class="indicator-value" id="page-load-time-value">-</div>
        <div class="indicator-status" id="page-load-time-status">
            <span class="status-dot"></span>
            <span class="status-text">Loading...</span>
        </div>
    </div>

    <div class="performance-indicator" data-metric="api-response-time">
        <div class="indicator-label">API Response Time</div>
        <div class="indicator-value" id="api-response-time-value">-</div>
        <div class="indicator-status" id="api-response-time-status">
            <span class="status-dot"></span>
            <span class="status-text">Loading...</span>
        </div>
    </div>

    <div class="performance-indicator" data-metric="memory-usage">
        <div class="indicator-label">Memory Usage</div>
        <div class="indicator-value" id="memory-usage-value">-</div>
        <div class="indicator-status" id="memory-usage-status">
            <span class="status-dot"></span>
            <span class="status-text">Loading...</span>
        </div>
    </div>

    <div class="performance-indicator" data-metric="network-health">
        <div class="indicator-label">Network Health</div>
        <div class="indicator-value" id="network-health-value">-</div>
        <div class="indicator-status" id="network-health-status">
            <span class="status-dot"></span>
            <span class="status-text">Loading...</span>
        </div>
    </div>
</div>

<style>
.performance-indicators {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.performance-indicator {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.indicator-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 8px;
}

.indicator-value {
    font-size: 20px;
    font-weight: 600;
    color: #2563eb;
    margin-bottom: 8px;
}

.indicator-status {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
    background-color: #ccc;
}

.status-dot.healthy { background-color: #10b981; }
.status-dot.warning { background-color: #f59e0b; }
.status-dot.critical { background-color: #ef4444; }

.status-text {
    color: #666;
}
</style>

<script>
// Performance indicators functionality
class PerformanceIndicators {
    constructor() {
        this.metrics = {};
        this.thresholds = {
            page_load_time: 500,
            api_response_time: 300,
            memory_usage: 100 * 1024 * 1024, // 100MB
            network_health: 300
        };
        this.init();
    }

    init() {
        this.loadMetrics();
        // Auto-refresh every 30 seconds
        setInterval(() => this.loadMetrics(), 30000);
    }

    async loadMetrics() {
        try {
            const response = await fetch('/api/admin/performance/dashboard', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateIndicators(data.data);
                }
            }
        } catch (error) {
            console.error('Failed to load performance metrics:', error);
        }
    }

    updateIndicators(data) {
        // Update page load time
        if (data.performance && data.performance.page_load_time) {
            const avgTime = data.performance.page_load_time.avg || 0;
            this.updateIndicator('page-load-time', avgTime, 'ms', avgTime < this.thresholds.page_load_time);
        }

        // Update API response time
        if (data.performance && data.performance.api_response_time) {
            const avgTime = data.performance.api_response_time.avg || 0;
            this.updateIndicator('api-response-time', avgTime, 'ms', avgTime < this.thresholds.api_response_time);
        }

        // Update memory usage
        if (data.memory && data.memory.current_usage) {
            const usage = data.memory.current_usage.current || 0;
            const usageMB = (usage / 1024 / 1024).toFixed(2);
            this.updateIndicator('memory-usage', usageMB, 'MB', usage < this.thresholds.memory_usage);
        }

        // Update network health
        if (data.network && data.network.response_time) {
            const avgTime = data.network.response_time.avg || 0;
            this.updateIndicator('network-health', avgTime, 'ms', avgTime < this.thresholds.network_health);
        }
    }

    updateIndicator(metric, value, unit, isHealthy) {
        const valueElement = document.getElementById(`${metric}-value`);
        const statusElement = document.getElementById(`${metric}-status`);
        const dotElement = statusElement.querySelector('.status-dot');
        const textElement = statusElement.querySelector('.status-text');

        if (valueElement) {
            valueElement.textContent = `${value}${unit}`;
        }

        if (dotElement && textElement) {
            const status = isHealthy ? 'healthy' : 'warning';
            dotElement.className = `status-dot ${status}`;
            textElement.textContent = isHealthy ? 'Good' : 'Slow';
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new PerformanceIndicators();
});
</script>
