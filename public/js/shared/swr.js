// Shared SWR + ETag Implementation for All Pages
class SWRCacheManager {
    constructor(defaultTTL = 30000) { // 30 seconds default
        this.cache = new Map();
        this.defaultTTL = defaultTTL;
        this.activeRequests = new Map();
    }

    async getWithETag(key, url, options = {}) {
        const { 
            ttl = this.defaultTTL, 
            signal, 
            onStart, 
            onEnd,
            forceRefresh = false 
        } = options;

        // Check active request to prevent duplicates
        if (this.activeRequests.has(key) && !forceRefresh) {
            console.log(`[SWR] Waiting for existing request: ${key}`);
            return this.activeRequests.get(key);
        }

        const cacheKey = `swr:${key}`;
        
        // Get cached data
        const cached = this.getCachedData(cacheKey);
        
        if (!forceRefresh && cached && this.isCacheValid(cached)) {
            console.log(`[SWR] Cache hit: ${key}`);
            
            // Background refresh without blocking
            this.refreshInBackground(key, url, { ttl, signal });
            
            return cached.data;
        }

        // Start fresh request
        console.log(`[SWR] Cache miss: ${key}, fetching...`);
        
        const requestPromise = this.fetchData(url, cached, { signal, onStart, onEnd })
            .then(data => {
                this.cacheResponse(cacheKey, data.data, data.etag, ttl);
                this.activeRequests.delete(key);
                return data.data;
            })
            .catch(error => {
                this.activeRequests.delete(key);
                throw error;
            });

        this.activeRequests.set(key, requestPromise);
        return requestPromise;
    }

    async fetchData(url, cachedData, options = {}) {
        const { signal, onStart, onEnd } = options;
        
        // Prepare headers for ETag
        const headers = { 'Accept': 'application/json' };
        if (cachedData?.etag) {
            headers['If-None-Match'] = cachedData.etag;
        }

        // Call onStart if provided
        if (onStart) onStart();

        try {
            const response = await fetch(url, signal ? { headers, signal } : { headers });
            
            // Handle 304 Not Modified
            if (response.status === 304 && cachedData?.data) {
                console.log(`[SWR] 304 Not Modified: ${url}`);
                if (onEnd) onEnd();
                return cachedData;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            const etag = response.headers.get('ETag');
            
            if (onEnd) onEnd();
            
            return { data, etag };
            
        } catch (error) {
            if (onEnd) onEnd();
            
            // Return cached data on error if available
            if (cachedData?.data) {
                console.warn(`[SWR] Request failed, using cached data: ${url}`, error.message);
                return cachedData;
            }
            
            throw error;
        }
    }

    // Background refresh without waiting
    async refreshInBackground(key, url, options = {}) {
        const { ttl, signal } = options;
        
        setTimeout(async () => {
            try {
                const result = await this.fetchData(url, this.getCachedData(`swr:${key}`), options);
                if (result.etag) {
                    this.cacheResponse(`swr:${key}`, result.data, result.etag, ttl);
                    console.log(`[SWR] Background refreshed: ${key}`);
                    
                    // Dispatch data updated event
                    document.dispatchEvent(new CustomEvent('swr:dataUpdated', {
                        detail: { key, data: result.data }
                    }));
                }
            } catch (error) {
                console.warn(`[SWR] Background refresh failed: ${key}`, error.message);
            }
        }, 100); // Small delay to avoid immediate refetch
    }

    getCachedData(key) {
        try {
            const cached = localStorage.getItem(key);
            return cached ? JSON.parse(cached) : null;
        } catch (error) {
            console.warn('[SWR] Error reading cache:', error);
            return null;
        }
    }

    cacheResponse(key, data, etag, ttl) {
        try {
            const cacheEntry = {
                data,
                etag,
                timestamp: Date.now(),
                ttl
            };
            localStorage.setItem(key, JSON.stringify(cacheEntry));
            
            // Also keep in memory for faster access
            this.cache.set(key, cacheEntry);
            
            // Prevent memory bloat
            if (this.cache.size > 100) {
                const firstKey = this.cache.keys().next().value;
                this.cache.delete(firstKey);
            }
        } catch (error) {
            console.warn('[SWR] Error caching data:', error);
        }
    }

    isCacheValid(cached) {
        if (!cached) return false;
        const now = Date.now();
        return (now - cached.timestamp) < cached.ttl;
    }

    // Clear cache
    clearCache(pattern = null) {
        if (pattern) {
            // Clear keys matching pattern
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('swr:') && key.includes(pattern)) {
                    localStorage.removeItem(key);
                }
            }
        } else {
            // Clear all SWR cache
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const key = localStorage.key(i);
                if (key && key.startsWith('swr:')) {
                    localStorage.removeItem(key);
                }
            }
            this.cache.clear();
        }
    }

    // Get cache stats
    getStats() {
        let cacheCount = 0;
        let totalSize = 0;
        
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('swr:')) {
                cacheCount++;
                totalSize += localStorage.getItem(key).length;
            }
        }
        
        return {
            count: cacheCount,
            memoryCount: this.cache.size,
            totalSize: Math.round(totalSize / 1024) + 'KB'
        };
    }
}

// Global instance
const swr = new SWRCacheManager(30000); // 30s default

// Convenience function
window.getWithETag = async function getWithETag(key, url, options = {}) {
    return swr.getWithETag(key, url, options);
};

// Expose globally for dashboard integration
if (typeof window !== 'undefined') {
    window.SWRCache = swr;
}

// Specialized helpers
window.getWithProgress = async function getWithProgress(key, url, options = {}) {
    const progressOptions = {
        ...options,
        onStart: () => {
            if (window.NProgress) window.NProgress.start();
            if (options.onStart) options.onStart();
        },
        onEnd: () => {
            if (window.NProgress) window.NProgress.done();
            if (options.onEnd) options.onEnd();
        }
    };
    
    return window.getWithETag(key, url, progressOptions);
};

// Final global exports
window.SWRCache = swr;

console.log('[SWR] Cache manager initialized');
