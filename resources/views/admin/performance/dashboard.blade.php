<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Performance Dashboard - ZenaManage</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .metric-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #2563eb;
        }
        .metric-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-healthy { background-color: #10b981; }
        .status-warning { background-color: #f59e0b; }
        .status-critical { background-color: #ef4444; }
        .recommendations {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .recommendation-item {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #2563eb;
            background-color: #f8fafc;
        }
        .recommendation-priority {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        .priority-high { color: #ef4444; }
        .priority-medium { color: #f59e0b; }
        .priority-low { color: #10b981; }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .refresh-btn {
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .refresh-btn:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Performance Dashboard</h1>
            <p>Real-time performance monitoring and metrics</p>
            <button class="refresh-btn" onclick="refreshMetrics()">Refresh Metrics</button>
        </div>

        <div id="loading" class="loading">
            Loading performance metrics...
        </div>

        <div id="error" class="error" style="display: none;">
            <strong>Error:</strong> <span id="error-message"></span>
        </div>

        <div id="metrics" style="display: none;">
            <div class="metrics-grid">
                <!-- Performance Metrics -->
                <div class="metric-card">
                    <div class="metric-title">Page Load Time</div>
                    <div class="metric-value" id="page-load-time">-</div>
                    <div class="metric-subtitle">
                        <span class="status-indicator" id="page-load-status"></span>
                        <span id="page-load-status-text">Loading...</span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-title">API Response Time</div>
                    <div class="metric-value" id="api-response-time">-</div>
                    <div class="metric-subtitle">
                        <span class="status-indicator" id="api-response-status"></span>
                        <span id="api-response-status-text">Loading...</span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-title">Memory Usage</div>
                    <div class="metric-value" id="memory-usage">-</div>
                    <div class="metric-subtitle">
                        <span class="status-indicator" id="memory-status"></span>
                        <span id="memory-status-text">Loading...</span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-title">Network Health</div>
                    <div class="metric-value" id="network-health">-</div>
                    <div class="metric-subtitle">
                        <span class="status-indicator" id="network-status"></span>
                        <span id="network-status-text">Loading...</span>
                    </div>
                </div>
            </div>

            <div class="recommendations">
                <h2>Performance Recommendations</h2>
                <div id="recommendations-list">
                    <div class="loading">Loading recommendations...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let refreshInterval;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            refreshMetrics();
            // Auto-refresh every 30 seconds
            refreshInterval = setInterval(refreshMetrics, 30000);
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });

        async function refreshMetrics() {
            try {
                const response = await fetch('/api/admin/performance/dashboard', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    updateMetrics(data.data);
                    hideError();
                } else {
                    showError(data.error || 'Failed to load metrics');
                }
            } catch (error) {
                showError(error.message);
            }
        }

        function updateMetrics(data) {
            // Update performance metrics
            if (data.performance) {
                updatePerformanceMetrics(data.performance);
            }

            // Update memory metrics
            if (data.memory) {
                updateMemoryMetrics(data.memory);
            }

            // Update network metrics
            if (data.network) {
                updateNetworkMetrics(data.network);
            }

            // Update recommendations
            if (data.recommendations) {
                updateRecommendations(data.recommendations);
            }

            // Show metrics and hide loading
            document.getElementById('loading').style.display = 'none';
            document.getElementById('metrics').style.display = 'block';
        }

        function updatePerformanceMetrics(performance) {
            // Page load time
            if (performance.page_load_time) {
                const avgTime = performance.page_load_time.avg || 0;
                document.getElementById('page-load-time').textContent = `${avgTime.toFixed(2)}ms`;
                
                const status = avgTime < 500 ? 'healthy' : (avgTime < 1000 ? 'warning' : 'critical');
                updateStatusIndicator('page-load', status, avgTime < 500 ? 'Good' : (avgTime < 1000 ? 'Slow' : 'Critical'));
            }

            // API response time
            if (performance.api_response_time) {
                const avgTime = performance.api_response_time.avg || 0;
                document.getElementById('api-response-time').textContent = `${avgTime.toFixed(2)}ms`;
                
                const status = avgTime < 300 ? 'healthy' : (avgTime < 600 ? 'warning' : 'critical');
                updateStatusIndicator('api-response', status, avgTime < 300 ? 'Good' : (avgTime < 600 ? 'Slow' : 'Critical'));
            }
        }

        function updateMemoryMetrics(memory) {
            if (memory.current_usage) {
                const usage = memory.current_usage.current || 0;
                const usageMB = (usage / 1024 / 1024).toFixed(2);
                document.getElementById('memory-usage').textContent = `${usageMB}MB`;
                
                const status = usage < 100 * 1024 * 1024 ? 'healthy' : (usage < 200 * 1024 * 1024 ? 'warning' : 'critical');
                updateStatusIndicator('memory', status, usage < 100 * 1024 * 1024 ? 'Good' : (usage < 200 * 1024 * 1024 ? 'High' : 'Critical'));
            }
        }

        function updateNetworkMetrics(network) {
            if (network.response_time) {
                const avgTime = network.response_time.avg || 0;
                document.getElementById('network-health').textContent = `${avgTime.toFixed(2)}ms`;
                
                const status = avgTime < 300 ? 'healthy' : (avgTime < 600 ? 'warning' : 'critical');
                updateStatusIndicator('network', status, avgTime < 300 ? 'Good' : (avgTime < 600 ? 'Slow' : 'Critical'));
            }
        }

        function updateStatusIndicator(type, status, text) {
            const indicator = document.getElementById(`${type}-status`);
            const textElement = document.getElementById(`${type}-status-text`);
            
            indicator.className = `status-indicator status-${status}`;
            textElement.textContent = text;
        }

        function updateRecommendations(recommendations) {
            const container = document.getElementById('recommendations-list');
            
            if (!recommendations.performance || recommendations.performance.length === 0) {
                container.innerHTML = '<div class="loading">No recommendations available</div>';
                return;
            }

            const html = recommendations.performance.map(rec => `
                <div class="recommendation-item">
                    <div class="recommendation-priority priority-${rec.priority}">${rec.priority}</div>
                    <div>${rec.message}</div>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        Current: ${rec.current_value} | Threshold: ${rec.threshold}
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function showError(message) {
            document.getElementById('error-message').textContent = message;
            document.getElementById('error').style.display = 'block';
            document.getElementById('loading').style.display = 'none';
            document.getElementById('metrics').style.display = 'none';
        }

        function hideError() {
            document.getElementById('error').style.display = 'none';
        }

        // Record page load time
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            fetch('/api/admin/performance/page-load', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    route: window.location.pathname,
                    load_time: loadTime
                })
            }).catch(error => {
                console.error('Failed to record page load time:', error);
            });
        });
    </script>
</body>
</html>
