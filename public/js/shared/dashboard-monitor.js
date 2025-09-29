/**
 * Dashboard Performance Monitor
 * Tracks metrics and provides insights for optimization
 */

class DashboardMonitor {
    constructor() {
        this.metrics = {
            loadTime: [],
            apiResponseTimes: new Map(),
            cacheHitRate: 0,
            refreshCount: 0,
            errorCount: 0,
            userInteractions: 0
        };
        
        this.thresholds = {
            maxLoadTime: 300, // ms
            maxApiResponseTime: 200, // ms
            minCacheHitRate: 0.8, // 80%
            maxErrorRate: 0.05 // 5%
        };
        
        this.init();
    }

    init() {
        this.setupPerformanceObserver();
        this.setupApiTiming();
        this.setupUserInteractionTracking();
        this.setupErrorTracking();
        
        // Log initial metrics
        console.log('[DashboardMonitor] Performance monitoring enabled');
    }

    /**
     * Monitor Core Web Vitals and other performance metrics
     */
    setupPerformanceObserver() {
        if ('PerformanceObserver' in window) {
            try {
                // Observe Long Task API
                const longTaskObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.duration > 50) {
                            console.warn('[Monitor] Long task detected:', {
                                duration: entry.duration,
                                startTime: entry.startTime
                            });
                        }
                    }
                });
                longTaskObserver.observe({ entryTypes: ['longtask'] });

                // Observe Layout Shift
                const clsObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.value > 0.1) {
                            console.warn('[Monitor] Layout shift detected:', {
                                value: entry.value,
                                sources: entry.sources
                            });
                        }
                    }
                });
                clsObserver.observe({ entryTypes: ['layout-shift'] });

            } catch (error) {
                console.warn('[DashboardMonitor] PerformanceObserver not fully supported:', error);
            }
        }
    }

    /**
     * Monitor API response times
     */
    setupApiTiming() {
        const originalFetch = window.fetch;
        
        window.fetch = async (url, options) => {
            const startTime = performance.now();
            
            try {
                const response = await originalFetch(url, options);
                const endTime = performance.now();
                const duration = endTime - startTime;
                
                this.recordApiResponse(url, duration, response.status);
                
                return response;
            } catch (error) {
                const endTime = performance.now();
                const duration = endTime - startTime;
                
                this.recordApiError(url, duration, error);
                throw error;
            }
        };
    }

    /**
     * Record API response metrics
     */
    recordApiResponse(url, duration, status) {
        const endpoint = this.getEndpointName(url);
        
        if (!this.metrics.apiResponseTimes.has(endpoint)) {
            this.metrics.apiResponseTimes.set(endpoint, []);
        }
        
        const times = this.metrics.apiResponseTimes.get(endpoint);
        times.push({ duration, status, timestamp: Date.now() });
        
        // Keep only last 50 responses
        if (times.length > 50) {
            times.splice(0, times.length - 50);
        }
        
        // Check threshold
        if (duration > this.thresholds.maxApiResponseTime) {
            console.warn(`[Monitor] Slow API response: ${endpoint} took ${duration.toFixed(2)}ms`);
        }
        
        // Log status errors
        if (status >= 400) {
            console.error(`[Monitor] API error: ${endpoint} returned ${status}`);
            this.metrics.errorCount++;
        }
    }

    /**
     * Record API errors
     */
    recordApiError(url, duration, error) {
        const endpoint = this.getEndpointName(url);
        console.error(`[Monitor] API fetch failed: ${endpoint}`, error);
        
        this.metrics.errorCount++;
        
        // Track for analytics
        this.trackEvent('api_error', {
            endpoint,
            duration,
            error: error.message
        });
    }

    /**
     * Track user interactions for UX insights
     */
    setupUserInteractionTracking() {
        const events = ['click', 'keydown', 'scroll'];
        
        events.forEach(eventType => {
            document.addEventListener(eventType, (event) => {
                this.metrics.userInteractions++;
                
                // Track specific dashboard interactions
                if (event.target.closest('[data-soft-refresh]')) {
                    this.trackEvent('soft_refresh_click', {
                        target: event.target.closest('[data-soft-refresh]').dataset.softRefresh
                    });
                }
                
                if (event.target.closest('[data-export]')) {
                    this.trackEvent('export_click', {
                        type: event.target.closest('[data-export]').dataset.export
                    });
                }
                
                if (event.target.closest('.kpi-panel')) {
                    this.trackEvent('kpi_interaction', {
                        testId: event.target.closest('.kpi-panel')?.dataset.testid
                    });
                }
            }, { passive: true });
        });
    }

    /**
     * Setup error tracking
     */
    setupErrorTracking() {
        // Monitor JavaScript errors
        window.addEventListener('error', (event) => {
            this.metrics.errorCount++;
            
            this.trackEvent('js_error', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            });
        });
        
        // Monitor unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.metrics.errorCount++;
            
            this.trackEvent('promise_rejection', {
                reason: event.reason?.toString()
            });
        });
    }

    /**
     * Record dashboard load time
     */
    recordDashboardLoad(timeStart, timeEnd) {
        const loadTime = timeEnd - timeStart;
        this.metrics.loadTime.push({
            duration: loadTime,
            timestamp: Date.now()
        });
        
        // Keep only last 20 load times
        if (this.metrics.loadTime.length > 20) {
            this.metrics.loadTime.splice(0, this.metrics.loadTime.length - 20);
        }
        
        console.log(`[Monitor] Dashboard loaded in ${loadTime.toFixed(2)}ms`);
        
        // Check threshold
        if (loadTime > this.thresholds.maxLoadTime) {
            console.warn(`[Monitor] Slow dashboard load: ${loadTime.toFixed(2)}ms exceeds ${this.thresholds.maxLoadTime}ms threshold`);
        }
    }

    /**
     * Record refresh events
     */
    recordRefresh(type = 'manual') {
        this.metrics.refreshCount++;
        
        this.trackEvent('dashboard_refresh', {
            type,
            count: this.metrics.refreshCount
        });
    }

    /**
     * Record cache hit/miss for hit rate calculation
     */
    recordCacheHit(hit) {
        const currentRate = this.metrics.cacheHitRate;
        const currentHits = currentRate * 100; // Assuming 100 samples base
        const totalSamples = 100;
        
        if (hit) {
            this.metrics.cacheHitRate = Math.min(1, (currentHits + 1) / (totalSamples + 1));
        } else {
            this.metrics.cacheHitRate = Math.max(0, currentHits / (totalSamples + 1));
        }
        
        if (this.metrics.cacheHitRate < this.thresholds.minCacheHitRate) {
            console.warn(`[Monitor] Low cache hit rate: ${(this.metrics.cacheHitRate * 100).toFixed(1)}%`);
        }
    }

    /**
     * Get performance summary
     */
    getPerformanceSummary() {
        const avgLoadTime = this.metrics.loadTime.length > 0
            ? this.metrics.loadTime.reduce((sum, entry) => sum + entry.duration, 0) / this.metrics.loadTime.length
            : 0;
        
        const avgApiTimes = {};
        for (const [endpoint, times] of this.metrics.apiResponseTimes) {
            if (times.length > 0) {
                avgApiTimes[endpoint] = times.reduce((sum, entry) => sum + entry.duration, 0) / times.length;
            }
        }
        
        return {
            summary: {
                avgLoadTime: Math.round(avgLoadTime),
                cacheHitRate: Math.round(this.metrics.cacheHitRate * 100),
                refreshCount: this.metrics.refreshCount,
                errorCount: this.metrics.errorCount,
                userInteractions: this.metrics.userInteractions
            },
            thresholds: this.thresholds,
            avgApiTimes,
            recommendations: this.getRecommendations()
        };
    }

    /**
     * Get performance recommendations
     */
    getRecommendations() {
        const recommendations = [];
        
        if (this.metrics.loadTime.length > 0) {
            const recentLoadTimes = this.metrics.loadTime.slice(-5);
            const avgRecent = recentLoadTimes.reduce((sum, entry) => sum + entry.duration, 0) / recentLoadTimes.length;
            
            if (avgRecent > this.thresholds.maxLoadTime) {
                recommendations.push({
                    type: 'performance',
                    message: `Dashboard load time (${Math.round(avgRecent)}ms) exceeds threshold`,
                    suggestion: 'Consider optimizing image loading or reducing initial data payload'
                });
            }
        }
        
        if (this.metrics.cacheHitRate < this.thresholds.minCacheHitRate) {
            recommendations.push({
                type: 'caching',
                message: `Cache hit rate (${Math.round(this.metrics.cacheHitRate * 100)}%) below optimal`,
                suggestion: 'Increase cache duration or optimize ETag implementation'
            });
        }
        
        const errorRate = this.metrics.errorCount / Math.max(1, this.metrics.refreshCount);
        if (errorRate > this.thresholds.maxErrorRate) {
            recommendations.push({
                type: 'reliability',
                message: `Error rate (${Math.round(errorRate * 100)}%) is high`,
                suggestion: 'Investigate network issues or API reliability'
            });
        }
        
        return recommendations;
    }

    /**
     * Helper methods
     */
    getEndpointName(url) {
        // Extract meaningful endpoint name from URL
        const urlObj = new URL(url, window.location.origin);
        return urlObj.pathname.replace('/api/', '');
    }

    trackEvent(eventName, properties = {}) {
        // Basic event tracking - can be extended to integrate with analytics
        const event = {
            name: eventName,
            timestamp: Date.now(),
            properties
        };
        
        console.log('[Monitor] Event:', event);
        
        // In production, send to analytics service
        // analytics.track(event.name, event.properties);
    }

    /**
     * Export metrics for analysis
     */
    exportMetrics() {
        const summary = this.getPerformanceSummary();
        
        const exportData = {
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            network: {
                connection: navigator.connection?.effectiveType || 'unknown',
                downlink: navigator.connection?.downlink || 'unknown'
            },
            ...summary
        };
        
        return exportData;
    }

    /**
     * Periodic reporting
     */
    startPeriodicReporting(intervalMs = 60000) { // Every minute
        setInterval(() => {
            const summary = this.getPerformanceSummary();
            
            // Log performance summary
            console.log('[Monitor] Performance Summary:', summary.summary);
            
            // Report recommendations
            if (summary.recommendations.length > 0) {
                console.log('[Monitor] Recommendations:', summary.recommendations);
            }
            
        }, intervalMs);
    }
}

// Initialize dashboard monitor
const dashboardMonitor = new DashboardMonitor();

// Auto-start periodic reporting
dashboardMonitor.startPeriodicReporting();

// Expose globally for debugging
window.DashboardMonitor = dashboardMonitor;

console.log('[Monitor] Dashboard performance monitoring initialized');
