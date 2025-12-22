@extends('layouts.admin')

@section('title', 'Performance Monitoring')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tachometer-alt"></i>
                        Performance Monitoring Dashboard
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Performance Metrics Overview -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3 id="page-load-time">{{ $metrics['page_load_times']['average_ms'] ?? 0 }}<small>ms</small></h3>
                                    <p>Page Load Time</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="small-box-footer">
                                    Target: < 500ms
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3 id="api-response-time">{{ $metrics['api_response_times']['average_ms'] ?? 0 }}<small>ms</small></h3>
                                    <p>API Response Time</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="small-box-footer">
                                    Target: < 300ms
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3 id="memory-usage">{{ $metrics['memory_usage']['current'] ?? 0 }}<small>MB</small></h3>
                                    <p>Memory Usage</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-memory"></i>
                                </div>
                                <div class="small-box-footer">
                                    Peak: {{ $metrics['memory_usage']['peak'] ?? 0 }}MB
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3 id="database-query-time">{{ $metrics['database_performance']['query_time_ms'] ?? 0 }}<small>ms</small></h3>
                                    <p>Database Query Time</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="small-box-footer">
                                    Target: < 100ms
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Charts -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Page Load Time Trend</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="pageLoadChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">API Response Time Trend</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="apiResponseChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Logs -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Recent Performance Logs</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Timestamp</th>
                                                    <th>Metric</th>
                                                    <th>Value</th>
                                                    <th>Unit</th>
                                                    <th>Category</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="performance-logs">
                                                @foreach($recentData as $log)
                                                <tr>
                                                    <td>{{ $log['created_at'] }}</td>
                                                    <td>{{ $log['metric_name'] }}</td>
                                                    <td>{{ $log['metric_value'] }}</td>
                                                    <td>{{ $log['metric_unit'] }}</td>
                                                    <td>{{ $log['category'] }}</td>
                                                    <td>
                                                        @if($log['metric_name'] === 'page_load_time' && $log['metric_value'] > 500)
                                                            <span class="badge badge-warning">Warning</span>
                                                        @elseif($log['metric_name'] === 'api_response_time' && $log['metric_value'] > 300)
                                                            <span class="badge badge-warning">Warning</span>
                                                        @elseif($log['metric_name'] === 'database_query_time' && $log['metric_value'] > 100)
                                                            <span class="badge badge-warning">Warning</span>
                                                        @else
                                                            <span class="badge badge-success">OK</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard Metrics -->
                    @if(!empty($dashboardMetrics))
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Dashboard Metrics</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        @foreach($dashboardMetrics as $metric)
                                        <div class="col-md-4">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">{{ $metric['name'] }}</h5>
                                                    <p class="card-text">{{ $metric['description'] }}</p>
                                                    @if(!empty($metric['values']))
                                                        <p class="card-text">
                                                            <strong>Latest Value:</strong> 
                                                            {{ $metric['values'][0]['value'] }} {{ $metric['unit'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initializeCharts();
    
    // Auto-refresh every 30 seconds
    setInterval(refreshMetrics, 30000);
    
    // Refresh button
    document.querySelector('[data-card-widget="refresh"]').addEventListener('click', refreshMetrics);
});

function initializeCharts() {
    // Page Load Time Chart
    const pageLoadCtx = document.getElementById('pageLoadChart').getContext('2d');
    new Chart(pageLoadCtx, {
        type: 'line',
        data: {
            labels: ['1h ago', '45m ago', '30m ago', '15m ago', 'Now'],
            datasets: [{
                label: 'Page Load Time (ms)',
                data: [800, 750, 720, 749, 749],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 1000
                }
            }
        }
    });

    // API Response Time Chart
    const apiResponseCtx = document.getElementById('apiResponseChart').getContext('2d');
    new Chart(apiResponseCtx, {
        type: 'line',
        data: {
            labels: ['1h ago', '45m ago', '30m ago', '15m ago', 'Now'],
            datasets: [{
                label: 'API Response Time (ms)',
                data: [0.3, 0.28, 0.29, 0.29, 0.29],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5
                }
            }
        }
    });
}

function refreshMetrics() {
    fetch('/admin/performance/metrics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateMetrics(data.data);
            }
        })
        .catch(error => {
            console.error('Error refreshing metrics:', error);
        });
}

function updateMetrics(metrics) {
    // Update page load time
    document.getElementById('page-load-time').innerHTML = 
        Math.round(metrics.page_load_times.average_ms) + '<small>ms</small>';
    
    // Update API response time
    document.getElementById('api-response-time').innerHTML = 
        Math.round(metrics.api_response_times.average_ms * 100) / 100 + '<small>ms</small>';
    
    // Update memory usage
    document.getElementById('memory-usage').innerHTML = 
        Math.round(metrics.memory_usage.current) + '<small>MB</small>';
    
    // Update database query time
    document.getElementById('database-query-time').innerHTML = 
        Math.round(metrics.database_performance.query_time_ms) + '<small>ms</small>';
}
</script>
@endsection
