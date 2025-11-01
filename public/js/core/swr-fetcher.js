/**
 * Core SWR Fetcher Module
 * Provides SWR-style caching with ETag, 304 handling, and abortable requests
 * Usage: fetchJSON(url, {signal, silent}), abortable(fn)
 */

// SWR Cache with TTL and compressed storage
const cache = {
    get: (key) => {
        const stored = localStorage.getItem(`swr_cache:${key}`);
        if (!stored) return null;
        
        try {
            const obj = JSON.parse(stored);
            // Cache expires after 30 seconds
            if (Date.now() - obj.timestamp > 30000) {
                localStorage.removeItem(`swr_cache:${key}`);
                return null;
            }
            return obj.data;
        } catch (e) {
            localStorage.removeItem(`swr_cache:${key}`);
            return null;
        }
    },
    
    set: (key, data) => {
        try {
            localStorage.setItem(`swr_cache:${key}`, JSON.stringify({
                timestamp: Date.now(),
                data: data
            }));
        } catch (e) {
            // Handle quota exceeded
            if (e.name === 'QuotaExceededError') {
                // Clear old cache entries
                this.clearOldEntries();
                // Retry once
                try {
                    localStorage.setItem(`swr_cache:${key}`, JSON.stringify({
                        timestamp: Date.now(),
                        data: data
                    }));
                } catch (e2) {
                    console.warn('Cache storage quota exceeded, skipping cache storage');
                }
            }
        }
    },
    
    clearOldEntries: () => {
        const keys = Object.keys(localStorage).filter(k => k.startsWith('swr_cache:'));
        const cutoff = Date.now() - 300000; // Remove entries older than 5 minutes
        
        keys.forEach(key => {
            try {
                const stored = localStorage.getItem(key);
                if (stored) {
                    const obj = JSON.parse(stored);
                    if (obj.timestamp < cutoff) {
                        localStorage.removeItem(key);
                    }
                }
            } catch (e) {
                // Remove corrupted entries
                localStorage.removeItem(key);
            }
        });
    }
};

// ETag cache for proper cache validation
const etagCache = {
    get: (url) => localStorage.getItem(`swr_etag:${url}`),
    set: (url, etag) => {
        try {
            localStorage.setItem(`swr_etag:${url}`, etag);
        } catch (e) {
            console.warn('ETag cache storage failed:', e);
        }
    }
};

/**
 * Enhanced fetch with caching, ETag and 304 handling
 * @param {string} url URL to fetch
 * @param {Object} options Fetch options
 * @param {AbortSignal} options.signal Abort signal
 * @param {boolean} options.silent Whether to suppress console logs
 * @param {Object} options.headers Additional headers
 * @returns {Promise<Object>} Response data with metadata
 */
export async function fetchJSON(url, options = {}) {
    const { signal, silent = false, headers = {} } = options;
    
    // Try cache first
    const cached = cache.get(url);
    const etag = etagCache.get(url);
    
    const fetchHeaders = {
        'Accept': 'application/json',
        ...headers
    };
    
    if (etag) {
        fetchHeaders['If-None-Match'] = etag;
    }
    
    try {
        const response = await fetch(url, {
            ...options,
            headers: fetchHeaders,
            signal
        });
        
        // Handle 304 Not Modified
        if (response.status === 304 && cached) {
            if (!silent) {
                console.log(`Cache hit (304) for ${url}`);
            }
            return {
                status: 304,
                data: cached,
                fromCache: true,
                url: url,
                timestamp: Date.now()
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
            fromCache: false,
            url: url,
            timestamp: Date.now()
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
                fromCache: true,
                url: url,
                timestamp: Date.now(),
                error: error.message
            };
        }
        
        throw error;
    }
}

/**
 * Create an abortable function with progress tracking
 * @param {Function} fn Function to make abortable
 * @returns {Object} Object with run() and abort() methods
 */
export function abortable(fn) {
    let controller = null;
    let isRunning = false;
    
    return {
        isRunning: () => isRunning,
        
        run: async (...args) => {
            // Abort previous if still running
            if (controller && isRunning) {
                controller.abort();
            }
            
            controller = new AbortController();
            isRunning = true;
            
            try {
                const result = await fn(controller.signal, ...args);
                return result;
            } finally {
                isRunning = false;
                controller = null;
            }
        },
        
        abort: () => {
            if (controller && isRunning) {
                controller.abort();
                isRunning = false;
                controller = null;
            }
        }
    };
}

/**
 * Create an abortable fetch wrapper for easy request management
 * @param {string} url URL to fetch
 * @param {Function} options Options object or function that returns options
 * @returns {Object} Abortable fetch object
 */
export function createAbortableFetch(url, options = {}) {
    return abortable(async (signal) => {
        const opts = typeof options === 'function' ? options() : options;
        return await fetchJSON(url, {
            ...opts,
            signal
        });
    });
}

/**
 * Batch fetch multiple URLs with individual abort control
 * @param {Array<Object>} requests Array of {url, options} objects
 * @returns {Object} Object with allResults and controllers
 */
export async function batchFetch(requests) {
    const controllers = new Map();
    const promises = requests.map(({ url, options = {}, key = url }, index) => {
        const controller = new AbortController();
        controllers.set(key || index, controller);
        
        return fetchJSON(url, {
            ...options,
            signal: controller.signal
        }).catch(error => ({
            error: error.message,
            url,
            status: 'error'
        }));
    });
    
    const results = await Promise.allSettled(promises);
    
    return {
        results: results.map((result, index) => ({
            ...result,
            request: requests[index]
        })),
        abortAll: () => {
            controllers.forEach(controller => controller.abort());
            controllers.clear();
        },
        abort: (key) => {
            const controller = controllers.get(key);
            if (controller) {
                controller.abort();
                controllers.delete(key);
            }
        }
    };
}

/**
 * Cache management utilities
 */
export const cacheUtils = {
    // Clear all cache entries
    clear: () => {
        const keys = Object.keys(localStorage).filter(k => 
            k.startsWith('swr_cache:') || k.startsWith('swr_etag:')
        );
        keys.forEach(key => localStorage.removeItem(key));
        console.log(`Cleared ${keys.length} cache entries`);
    },
    
    // Get cache statistics
    stats: () => {
        const cacheKeys = Object.keys(localStorage).filter(k => k.startsWith('swr_cache:'));
        const etagKeys = Object.keys(localStorage).filter(k => k.startsWith('swr_etag:'));
        
        let cacheSize = 0;
        let etagSize = 0;
        
        cacheKeys.forEach(key => {
            cacheSize += localStorage.getItem(key)?.length || 0;
        });
        
        etagKeys.forEach(key => {
            etagSize += localStorage.getItem(key)?.length || 0;
        });
        
        return {
            cacheEntries: cacheKeys.length,
            etagEntries: etagKeys.length,
            cacheSizeBytes: cacheSize,
            etagSizeBytes: etagSize,
            totalSizeBytes: cacheSize + etagSize
        };
    },
    
    // Preload URLs into cache (useful for critical data)
    preload: async (urls) => {
        const results = await Promise.allSettled(
            urls.map(url => fetchJSON(url, { silent: true }))
        );
        
        const successes = results.filter(r => r.status === 'fulfilled').length;
        console.log(`Preloaded ${successes}/${urls.length} URLs into cache`);
        
        return results;
    }
};

// Export default for compatibility
export default { fetchJSON, abortable, createAbortableFetch, batchFetch, cacheUtils };
