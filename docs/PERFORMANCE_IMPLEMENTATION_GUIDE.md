# Performance & Monitoring Implementation Guide

## Overview
This document outlines the implementation of comprehensive performance monitoring and metrics collection in ZenaManage, providing real-time performance insights, memory monitoring, network monitoring, and performance recommendations.

## Architecture

### Core Components
- **PerformanceMonitoringService**: Central service for performance metrics collection and analysis
- **MemoryMonitoringService**: Memory usage monitoring and garbage collection management
- **NetworkMonitoringService**: Network performance monitoring and connectivity testing
- **PerformanceController**: API endpoints for performance data access and management
- **PerformanceLoggingMiddleware**: Automatic performance logging for all requests

### Performance Budgets
- **Page Load Time**: p95 < 500ms
- **API Response Time**: p95 < 300ms
- **Memory Usage**: Warning at 70%, Critical at 85%
- **Database Query Time**: < 100ms
- **Cache Hit Ratio**: > 90%

## Implementation Details

### 1. PerformanceMonitoringService
```php
<?php declare(strict_types=1);

namespace App\Services;

class PerformanceMonitoringService
{
    // Core metrics recording
    public function recordPageLoadTime(string $route, float $loadTime): void
    public function recordApiResponseTime(string $endpoint, float $responseTime): void
    public function recordMemoryUsage(float $memoryUsage): void
    public function recordDatabaseQueryTime(string $query, float $queryTime): void
    public function recordCacheHitRatio(string $cacheKey, bool $hit): void
    public function recordError(string $error, string $context = ''): void
    public function recordThroughput(string $operation, int $count): void
    
    // Statistics and analysis
    public function getPerformanceStats(): array
    public function getPerformanceRecommendations(): array
    public function getPerformanceThresholds(): array
    public function setPerformanceThresholds(array $thresholds): void
    
    // Data management
    public function clearMetrics(): void
    public function exportPerformanceData(): array
    public function getRealTimeMetrics(): array
}
```

### 2. MemoryMonitoringService
```php
<?php declare(strict_types=1);

namespace App\Services;

class MemoryMonitoringService
{
    // Memory monitoring
    public function getCurrentMemoryUsage(): array
    public function recordMemoryUsage(): void
    public function getMemoryStats(): array
    public function getMemoryRecommendations(): array
    
    // Memory management
    public function forceGarbageCollection(): array
    public function getMemoryUsageByClass(): array
    
    // Configuration
    public function getMemoryThresholds(): array
    public function setMemoryThresholds(array $thresholds): void
    public function clearHistory(): void
    public function exportMemoryData(): array
}
```

### 3. NetworkMonitoringService
```php
<?php declare(strict_types=1);

namespace App\Services;

class NetworkMonitoringService
{
    // Network monitoring
    public function monitorApiEndpoint(string $url, array $options = []): array
    public function recordResponseTime(string $url, float $responseTime): void
    public function recordError(string $url, string $error, mixed $context = null): void
    public function recordTimeout(string $url, float $timeout): void
    public function recordThroughput(string $url, int $requests): void
    
    // Analysis and health
    public function getNetworkStats(): array
    public function getNetworkRecommendations(): array
    public function getNetworkHealthStatus(): array
    public function testConnectivity(string $url): array
    
    // Configuration
    public function getNetworkThresholds(): array
    public function setNetworkThresholds(array $thresholds): void
    public function clearHistory(): void
    public function exportNetworkData(): array
}
```

### 4. API Endpoints
```php
// Performance monitoring routes
Route::prefix('admin/performance')->group(function () {
    Route::get('/dashboard', [PerformanceController::class, 'getDashboard']);
    Route::get('/stats', [PerformanceController::class, 'getPerformanceStats']);
    Route::get('/memory', [PerformanceController::class, 'getMemoryStats']);
    Route::get('/network', [PerformanceController::class, 'getNetworkStats']);
    Route::get('/recommendations', [PerformanceController::class, 'getRecommendations']);
    Route::get('/thresholds', [PerformanceController::class, 'getThresholds']);
    Route::post('/thresholds', [PerformanceController::class, 'setThresholds']);
    Route::post('/page-load', [PerformanceController::class, 'recordPageLoadTime']);
    Route::post('/api-response', [PerformanceController::class, 'recordApiResponseTime']);
    Route::post('/memory', [PerformanceController::class, 'recordMemoryUsage']);
    Route::post('/network-monitor', [PerformanceController::class, 'monitorNetworkEndpoint']);
    Route::get('/realtime', [PerformanceController::class, 'getRealTimeMetrics']);
    Route::post('/clear', [PerformanceController::class, 'clearData']);
    Route::get('/export', [PerformanceController::class, 'exportData']);
    Route::post('/gc', [PerformanceController::class, 'forceGarbageCollection']);
    Route::post('/test-connectivity', [PerformanceController::class, 'testConnectivity']);
    Route::get('/network-health', [PerformanceController::class, 'getNetworkHealthStatus']);
});
```

### 5. Web Routes
```php
// Admin Performance Routes
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/admin/performance', function () {
        return view('admin.performance.dashboard');
    })->name('admin.performance');
    Route::get('/admin/performance/metrics', [PerformanceController::class, 'getDashboard']);
    Route::get('/admin/performance/logs', [PerformanceController::class, 'getRealTimeMetrics']);
    Route::post('/admin/performance/metrics', [PerformanceController::class, 'recordPageLoadTime']);
});
```

### 6. Blade Components

#### Performance Indicators
```html
<div class="performance-indicators">
    <div class="performance-indicator" data-metric="page-load-time">
        <div class="indicator-label">Page Load Time</div>
        <div class="indicator-value" id="page-load-time-value">-</div>
        <div class="indicator-status" id="page-load-time-status">
            <span class="status-dot"></span>
            <span class="status-text">Loading...</span>
        </div>
    </div>
    <!-- More indicators... -->
</div>
```

#### Loading Time Display
```html
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
        <!-- More metrics... -->
    </div>
    
    <div class="loading-time-chart">
        <canvas id="loading-time-chart" width="400" height="200"></canvas>
    </div>
</div>
```

#### API Timing Display
```html
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
        <!-- More metrics... -->
    </div>
    
    <div class="api-endpoints">
        <h4>Endpoint Performance</h4>
        <div id="api-endpoints-list">
            <div class="loading">Loading endpoints...</div>
        </div>
    </div>
</div>
```

#### Performance Monitor
```html
<div class="performance-monitor">
    <div class="performance-monitor-header">
        <h3>Performance Monitor</h3>
        <div class="monitor-controls">
            <button class="control-btn" onclick="startMonitoring()">Start</button>
            <button class="control-btn" onclick="stopMonitoring()">Stop</button>
            <button class="control-btn" onclick="clearMonitoring()">Clear</button>
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
            <!-- More metrics... -->
        </div>
    </div>
    
    <div class="monitor-chart">
        <canvas id="performance-chart" width="800" height="400"></canvas>
    </div>
</div>
```

## Usage Examples

### 1. Recording Performance Metrics
```php
// Backend - Automatic via middleware
$performanceService = app(PerformanceMonitoringService::class);
$performanceService->recordPageLoadTime('/dashboard', 250.5);
$performanceService->recordApiResponseTime('/api/users', 150.3);
$performanceService->recordMemoryUsage(memory_get_usage(true));
```

### 2. Frontend Integration
```javascript
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
    });
});

// Monitor API calls
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const startTime = performance.now();
    return originalFetch.apply(this, args).then(response => {
        const endTime = performance.now();
        const responseTime = endTime - startTime;
        
        // Record API timing
        fetch('/api/admin/performance/api-response', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                endpoint: args[0],
                response_time: responseTime
            })
        });
        
        return response;
    });
};
```

### 3. Getting Performance Data
```javascript
// Get performance dashboard
fetch('/api/admin/performance/dashboard')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Performance:', data.data.performance);
            console.log('Memory:', data.data.memory);
            console.log('Network:', data.data.network);
            console.log('Recommendations:', data.data.recommendations);
        }
    });

// Get real-time metrics
fetch('/api/admin/performance/realtime')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updatePerformanceIndicators(data.data);
        }
    });
```

## Testing

### Unit Tests
- `PerformanceServiceTest` - 32 tests covering all performance services
- Performance metrics recording and analysis
- Memory monitoring and garbage collection
- Network monitoring and connectivity testing
- Thresholds and recommendations
- Data export and clearing

### Feature Tests
- `PerformanceFeatureTest` - 25 tests covering API endpoints
- Dashboard data retrieval
- Performance metrics recording
- Validation and error handling
- Authentication requirements
- Complete workflow testing

## Performance Monitoring Features

### 1. Real-time Metrics
- Page load time tracking
- API response time monitoring
- Memory usage monitoring
- Network performance tracking
- Database query time analysis
- Cache hit ratio monitoring

### 2. Performance Recommendations
- Automatic performance analysis
- Threshold-based recommendations
- Priority-based suggestions (high, medium, low)
- Actionable optimization tips
- Performance budget compliance

### 3. Performance Dashboard
- Real-time performance indicators
- Historical performance charts
- Performance alerts and warnings
- Threshold configuration
- Export capabilities

### 4. Memory Management
- Current memory usage tracking
- Peak memory monitoring
- Memory limit analysis
- Garbage collection management
- Memory usage by class analysis

### 5. Network Monitoring
- API endpoint monitoring
- Response time tracking
- Error rate monitoring
- Timeout detection
- Connectivity testing
- Network health scoring

## Security Considerations

### Input Validation
- All performance data validated before recording
- URL validation for network monitoring
- Numeric validation for timing data
- Array validation for threshold configuration

### Error Handling
- Graceful error handling with proper HTTP status codes
- ValidationException handling for 422 responses
- Generic exception handling for 500 responses
- Structured error responses with details

### Authentication
- All performance endpoints require authentication
- Admin-level access for performance data
- CSRF protection for state-changing operations

## Performance Considerations

### Caching Strategy
- Performance metrics cached for 5 minutes
- Real-time metrics stored in cache
- Cache keys for efficient retrieval
- Automatic cache cleanup

### Memory Management
- Efficient memory usage tracking
- Garbage collection optimization
- Memory leak detection
- Peak memory monitoring

### Database Optimization
- Minimal database queries for metrics
- Efficient data storage
- Indexed performance data
- Query optimization recommendations

## Troubleshooting

### Common Issues

1. **Performance metrics not recording**
   - Check middleware registration
   - Verify service injection
   - Check cache configuration

2. **Memory monitoring issues**
   - Verify memory_get_usage() availability
   - Check memory limit configuration
   - Verify garbage collection settings

3. **Network monitoring failures**
   - Check HTTP client configuration
   - Verify URL accessibility
   - Check timeout settings

4. **Dashboard not loading**
   - Check authentication
   - Verify API endpoint accessibility
   - Check JavaScript console for errors

### Debug Mode
Enable debug logging in services:
```php
Log::info('Performance metric recorded', [
    'type' => 'page_load_time',
    'value' => $loadTime,
    'route' => $route
]);
```

## Future Enhancements

### Planned Features
1. **Advanced Analytics**: Machine learning-based performance predictions
2. **Custom Dashboards**: User-configurable performance dashboards
3. **Performance Alerts**: Email/SMS notifications for performance issues
4. **Performance Reports**: Automated performance reports
5. **Performance Optimization**: Automatic performance optimization suggestions

### Integration Points
- User dashboard integration
- Email notification system
- Mobile app performance monitoring
- Third-party monitoring tools integration
- Performance benchmarking

## Conclusion

The performance monitoring implementation provides comprehensive real-time performance insights for ZenaManage, enabling proactive performance management and optimization. The system includes automatic performance logging, real-time monitoring, performance recommendations, and a user-friendly dashboard for performance analysis.

The implementation follows Laravel best practices and provides a solid foundation for future performance monitoring enhancements and optimizations.
