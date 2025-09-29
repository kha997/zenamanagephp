/**
 * Soft Refresh for Security Page
 * Prevents full page reload when clicking Security while already on /admin/security
 * Implements SWR-style caching with ETag and in-place data refresh
 */

// SWR Cache with TTL
const cache = {
    get: (key) => {
        const stored = localStorage.getItem(`security_cache:${key}`);
        if (!stored) return null;
        
        try {
            const obj = JSON.parse(stored);
            // Cache expires after 30 seconds
            if (Date.now() - obj.timestamp > 30000) {
                localStorage.removeItem(`security_cache:${key}`);
                return null;
            }
            return obj.data;
        } catch (e) {
            localStorage.removeItem(`security_cache:${key}`);
            return null;
        }
    },
    
    set: (key, data) => {
        localStorage.setItem(`security_cache:${key}`, JSON.stringify({
            timestamp: Date.now(),
            data: data
        }));
    }
};

// ETag cache
const etagCache = {
    get: (url) => localStorage.getItem(`security_etag:${url}`),
    set: (url, etag) => localStorage.setItem(`security_etag:${url}`, etag)
};

// Enhanced fetch with caching, ETag and 304 handling
async function fetchWithCache(url, options = {}) {
    const { silent = false, signal } = options;
    
    // Try cache first
    const cached = cache.get(url);
    const etag = etagCache.get(url);
    
    const headers = {
        'Accept': 'application/json',
        ...options.headers
    };
    
    if (etag) {
        headers['If-None-Match'] = etag;
    }
    
    try {
        const response = await fetch(url, {
            ...options,
            headers,
            signal
        });
        
        // Handle 304 Not Modified
        if (response.status === 304 && cached) {
            if (!silent) {
                console.log(`Cache hit for ${url}`);
            }
            return {
                status: 304,
                data: cached,
                fromCache: true
            };
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        // Store in cache
        cache.set(url, data);
        
        // Store ETag
        const newEtag = response.headers.get('ETag');
        if (newEtag) {
            etagCache.set(url, newEtag);
        }
        
        return {
            status: response.status,
            data: data,
            fromCache: false
        };
        
    } catch (error) {
        if (error.name === 'AbortError') {
            throw error;
        }
        
        // Fall back to cache on network error
        if (cached && !silent) {
            console.warn(`Network error for ${url}, using cache:`, error.message);
            return {
                status: 0,
                data: cached,
                fromCache: true
            };
        }
        
        throw error;
    }
}

// In-flight request tracking
const inFlightRequests = {};

// Cancelable fetch wrappers
async function fetchKpis(options = {}) {
    const url = `/api/admin/security/kpis-bypass?period=${options.period || '30d'}`;
    
    if (inFlightRequests.kpis) {
        inFlightRequests.kpis.abort();
    }
    
    const controller = new AbortController();
    inFlightRequests.kpis = controller;
    
    try {
        const result = await fetchWithCache(url, {
            ...options,
            signal: controller.signal,
            silent: true
        });
        return result;
    } finally {
        delete inFlightRequests.kpis;
    }
}

async function fetchMfaUsers(options = {}) {
    const url = `/api/admin/security/mfa-bypass?page=${options.page || 1}&per_page=${options.perPage || 100}`;
    
    if (inFlightRequests.mfa) {
        inFlightRequests.mfa.abort();
    }
    
    const controller = new AbortController();
    inFlightRequests.mfa = controller;
    
    try {
        const result = await fetchWithCache(url, {
            ...options,
            signal: controller.signal,
            silent: true
        });
        return result;
    } finally {
        delete inFlightRequests.mfa;
    }
}

async function fetchLoginAttempts(options = {}) {
    const url = `/api/admin/security/logins-bypass?period=${options.period || '30d'}`;
    
    if (inFlightRequests.logins) {
        inFlightRequests.logins.abort();
    }
    
    const controller = new AbortController();
    inFlightRequests.logins = controller;
    
    try {
        const result = await fetchWithCache(url, {
            ...options,
            signal: controller.signal,
            silent: true
        });
        return result;
    } finally {
        delete inFlightRequests.logins;
    }
}

async function fetchAuditLogs(options = {}) {
    const params = new URLSearchParams({
        page: options.page || 1,
        per_page: options.perPage || 50,
        range: options.range || '24h'
    });
    
    if (options.severity) params.append('severity', options.severity);
    if (options.q) params.append('q', options.q);
    
    const url = `/api/admin/security/audit-bypass?${params.toString()}`;
    
    if (inFlightRequests.audit) {
        inFlightRequests.audit.abort();
    }
    
    const controller = new AbortController();
    inFlightRequests.audit = controller;
    
    try {
        const result = await fetchWithCache(url, {
            ...options,
            signal: controller.signal,
            silent: true
        });
        return result;
    } finally {
        delete inFlightRequests.audit;
    }
}

// Global Security refresh API
window.Security = window.Security || {};

window.Security.refresh = async function(options = {}) {
    const startTime = Date.now();
    const period = localStorage.getItem('security_period') || '30d';
    
    // Add subtle reloading state
    document.body.classList.add('is-reloading');
    
    try {
        console.log('Starting soft refresh...');
        
        // Fetch all data in parallel
        const results = await Promise.allSettled([
            fetchKpis({ period }),
            fetchMfaUsers(),
            fetchLoginAttempts({ period }),
            fetchAuditLogs({ range: period })
        ]);
        
        const [kpisResult, mfaResult, loginsResult, auditResult] = results;
        
        // Dispatch custom events for page components to update
        if (kpisResult.status === 'fulfilled') {
            window.dispatchEvent(new CustomEvent('security:kpisUpdated', { 
                detail: kpisResult.value.data 
            }));
        }
        
        if (mfaResult.status === 'fulfilled') {
            window.dispatchEvent(new CustomEvent('security:mfaUpdated', { 
                detail: mfaResult.value.data 
            }));
        }
        
        if (loginsResult.status === 'fulfilled') {
            window.dispatchEvent(new CustomEvent('security:loginsUpdated', { 
                detail: loginsResult.value.data 
            }));
        }
        
        if (auditResult.status === 'fulfilled') {
            window.dispatchEvent(new CustomEvent('security:auditUpdated', { 
                detail: auditResult.value.data 
            }));
        }
        
        const duration = Date.now() - startTime;
        console.log(`Soft refresh completed in ${duration}ms`);
        
        // Show subtle notification if it took longer than expected
        if (duration > 500) {
            console.log('Refresh took longer than expected, some requests may have been slow');
        }
        
    } catch (error) {
        console.error('Soft refresh error:', error);
        
        // Notify error state
        window.dispatchEvent(new CustomEvent('security:refreshError', { 
            detail: { error: error.message } 
        }));
        
    } finally {
        // Remove reloading state with a slight delay for visual feedback
        setTimeout(() => {
            document.body.classList.remove('is-reloading');
        }, 100);
    }
};

// Initialize soft refresh handler
document.addEventListener('DOMContentLoaded', function() {
    // Handle soft refresh clicks
    document.querySelectorAll('[data-soft-refresh="security"]').forEach(link => {
        link.addEventListener('click', function(e) {
            // Only intercept if we're already on the security page
            if (location.pathname === '/admin/security') {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Intercepted Security navigation for soft refresh');
                window.Security.refresh();
                return false;
            }
        });
    });
});

// Export for potential module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { cache, etagCache, fetchWithCache, window.Security };
}
