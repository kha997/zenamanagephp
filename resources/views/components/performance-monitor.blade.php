<div class="performance-monitor">
    <div class="performance-monitor-header">
        <h3>Performance Monitor</h3>
        <div class="monitor-controls">
            <button class="control-btn" onclick="startMonitoring()">Start</button>
            <button class="control-btn" onclick="stopMonitoring()">Stop</button>
            <button class="control-btn" onclick="clearMonitoring()">Clear</button>
        </div>
    </div>
    
    <div class="monitor-status">
        <div class="status-item">
            <span class="status-label">Monitoring:</span>
            <span class="status-value" id="monitoring-status">Stopped</span>
        </div>
        <div class="status-item">
            <span class="status-label">Duration:</span>
            <span class="status-value" id="monitoring-duration">00:00:00</span>
        </div>
        <div class="status-item">
            <span class="status-label">Samples:</span>
            <span class="status-value" id="monitoring-samples">0</span>
        </div>
    </div>
    
    <div class="monitor-metrics">
        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-title">CPU Usage</div>
                <div class="metric-value" id="cpu-usage">-</div>
                <div class="metric-progress">
                    <div class="progress-bar" id="cpu-progress"></div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Memory Usage</div>
                <div class="metric-value" id="memory-usage">-</div>
                <div class="metric-progress">
                    <div class="progress-bar" id="memory-progress"></div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Network I/O</div>
                <div class="metric-value" id="network-io">-</div>
                <div class="metric-progress">
                    <div class="progress-bar" id="network-progress"></div>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Disk I/O</div>
                <div class="metric-value" id="disk-io">-</div>
                <div class="metric-progress">
                    <div class="progress-bar" id="disk-progress"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="monitor-chart">
        <canvas id="performance-chart" width="800" height="400"></canvas>
    </div>
    
    <div class="monitor-alerts">
        <h4>Performance Alerts</h4>
        <div id="alerts-list">
            <div class="loading">No alerts</div>
        </div>
    </div>
</div>

<style>
.performance-monitor {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.performance-monitor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.performance-monitor-header h3 {
    margin: 0;
    color: #333;
}

.monitor-controls {
    display: flex;
    gap: 10px;
}

.control-btn {
    background-color: #2563eb;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.control-btn:hover {
    background-color: #1d4ed8;
}

.control-btn:disabled {
    background-color: #9ca3af;
    cursor: not-allowed;
}

.monitor-status {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8fafc;
    border-radius: 6px;
}

.status-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.status-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.status-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.monitor-metrics {
    margin-bottom: 20px;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.metric-card {
    padding: 15px;
    background-color: #f8fafc;
    border-radius: 6px;
    text-align: center;
}

.metric-title {
    font-size: 14px;
    color: #666;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 18px;
    font-weight: 600;
    color: #2563eb;
    margin-bottom: 8px;
}

.metric-progress {
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background-color: #2563eb;
    transition: width 0.3s ease;
    width: 0%;
}

.progress-bar.warning { background-color: #f59e0b; }
.progress-bar.critical { background-color: #ef4444; }

.monitor-chart {
    margin: 20px 0;
    text-align: center;
}

.monitor-alerts {
    margin-top: 20px;
}

.monitor-alerts h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.alert-item {
    padding: 10px 15px;
    margin: 8px 0;
    border-radius: 6px;
    font-size: 14px;
}

.alert-item.warning {
    background-color: #fef3c7;
    border-left: 4px solid #f59e0b;
    color: #92400e;
}

.alert-item.critical {
    background-color: #fef2f2;
    border-left: 4px solid #ef4444;
    color: #dc2626;
}

.loading {
    text-align: center;
    color: #666;
    padding: 20px;
}
</style>

<script>
class PerformanceMonitor {
    constructor() {
        this.isMonitoring = false;
        this.monitoringInterval = null;
        this.startTime = null;
        this.samples = [];
        this.chart = null;
        this.alerts = [];
        this.init();
    }

    init() {
        this.initChart();
        this.updateStatus();
    }

    startMonitoring() {
        if (this.isMonitoring) return;
        
        this.isMonitoring = true;
        this.startTime = Date.now();
        this.samples = [];
        this.alerts = [];
        
        this.monitoringInterval = setInterval(() => {
            this.collectSample();
        }, 1000); // Collect sample every second
        
        this.updateStatus();
        this.updateAlerts();
    }

    stopMonitoring() {
        if (!this.isMonitoring) return;
        
        this.isMonitoring = false;
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
            this.monitoringInterval = null;
        }
        
        this.updateStatus();
    }

    clearMonitoring() {
        this.stopMonitoring();
        this.samples = [];
        this.alerts = [];
        this.updateStatus();
        this.updateChart();
        this.updateAlerts();
    }

    collectSample() {
        const sample = {
            timestamp: Date.now(),
            cpu: this.getCpuUsage(),
            memory: this.getMemoryUsage(),
            network: this.getNetworkUsage(),
            disk: this.getDiskUsage()
        };
        
        this.samples.push(sample);
        
        // Keep only last 100 samples
        if (this.samples.length > 100) {
            this.samples = this.samples.slice(-100);
        }
        
        this.updateMetrics(sample);
        this.updateChart();
        this.checkAlerts(sample);
    }

    getCpuUsage() {
        // Simulate CPU usage (in real implementation, this would be actual CPU monitoring)
        return Math.random() * 100;
    }

    getMemoryUsage() {
        const memory = performance.memory;
        if (memory) {
            return (memory.usedJSHeapSize / memory.totalJSHeapSize) * 100;
        }
        return Math.random() * 100;
    }

    getNetworkUsage() {
        // Simulate network usage
        return Math.random() * 100;
    }

    getDiskUsage() {
        // Simulate disk usage
        return Math.random() * 100;
    }

    updateMetrics(sample) {
        document.getElementById('cpu-usage').textContent = `${sample.cpu.toFixed(1)}%`;
        document.getElementById('memory-usage').textContent = `${sample.memory.toFixed(1)}%`;
        document.getElementById('network-io').textContent = `${sample.network.toFixed(1)}%`;
        document.getElementById('disk-io').textContent = `${sample.disk.toFixed(1)}%`;
        
        this.updateProgressBar('cpu-progress', sample.cpu);
        this.updateProgressBar('memory-progress', sample.memory);
        this.updateProgressBar('network-progress', sample.network);
        this.updateProgressBar('disk-progress', sample.disk);
    }

    updateProgressBar(elementId, value) {
        const progressBar = document.getElementById(elementId);
        if (!progressBar) return;
        
        progressBar.style.width = `${value}%`;
        
        // Update color based on value
        progressBar.className = 'progress-bar';
        if (value > 80) {
            progressBar.classList.add('critical');
        } else if (value > 60) {
            progressBar.classList.add('warning');
        }
    }

    updateStatus() {
        const statusElement = document.getElementById('monitoring-status');
        const durationElement = document.getElementById('monitoring-duration');
        const samplesElement = document.getElementById('monitoring-samples');
        
        if (statusElement) {
            statusElement.textContent = this.isMonitoring ? 'Running' : 'Stopped';
        }
        
        if (durationElement) {
            if (this.isMonitoring && this.startTime) {
                const duration = Date.now() - this.startTime;
                const hours = Math.floor(duration / 3600000);
                const minutes = Math.floor((duration % 3600000) / 60000);
                const seconds = Math.floor((duration % 60000) / 1000);
                durationElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                durationElement.textContent = '00:00:00';
            }
        }
        
        if (samplesElement) {
            samplesElement.textContent = this.samples.length.toString();
        }
    }

    initChart() {
        const canvas = document.getElementById('performance-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        this.chart = {
            canvas: canvas,
            ctx: ctx
        };
    }

    updateChart() {
        if (!this.chart || this.samples.length < 2) return;

        const ctx = this.chart.ctx;
        const canvas = this.chart.canvas;
        const width = canvas.width;
        const height = canvas.height;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        // Draw grid
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 10; i++) {
            const y = (height / 10) * i;
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(width, y);
            ctx.stroke();
        }

        // Draw CPU line
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 2;
        ctx.beginPath();
        this.samples.forEach((sample, index) => {
            const x = (index / (this.samples.length - 1)) * width;
            const y = height - (sample.cpu / 100) * height;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();

        // Draw Memory line
        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 2;
        ctx.beginPath();
        this.samples.forEach((sample, index) => {
            const x = (index / (this.samples.length - 1)) * width;
            const y = height - (sample.memory / 100) * height;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();
    }

    checkAlerts(sample) {
        const thresholds = {
            cpu: 80,
            memory: 85,
            network: 90,
            disk: 95
        };

        Object.keys(thresholds).forEach(metric => {
            if (sample[metric] > thresholds[metric]) {
                const alert = {
                    type: 'warning',
                    metric: metric,
                    value: sample[metric],
                    threshold: thresholds[metric],
                    timestamp: new Date()
                };
                
                this.alerts.push(alert);
            }
        });

        this.updateAlerts();
    }

    updateAlerts() {
        const container = document.getElementById('alerts-list');
        if (!container) return;

        if (this.alerts.length === 0) {
            container.innerHTML = '<div class="loading">No alerts</div>';
            return;
        }

        const html = this.alerts.slice(-10).reverse().map(alert => `
            <div class="alert-item ${alert.type}">
                <strong>${alert.metric.toUpperCase()}</strong> usage is ${alert.value.toFixed(1)}% 
                (threshold: ${alert.threshold}%) - ${alert.timestamp.toLocaleTimeString()}
            </div>
        `).join('');

        container.innerHTML = html;
    }
}

// Global functions for control buttons
function startMonitoring() {
    if (window.performanceMonitor) {
        window.performanceMonitor.startMonitoring();
    }
}

function stopMonitoring() {
    if (window.performanceMonitor) {
        window.performanceMonitor.stopMonitoring();
    }
}

function clearMonitoring() {
    if (window.performanceMonitor) {
        window.performanceMonitor.clearMonitoring();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.performanceMonitor = new PerformanceMonitor();
});
</script>
