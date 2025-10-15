// Cleanup Manager - Remove floating UI artifacts and maintain clean state
class CleanupManager {
    constructor() {
        this.observers = new Map();
        this.timers = new Map();
        this.init();
    }

    init() {
        this.setupGlobalCleanup();
        this.setupBodyStateCleanup();
        console.log('[Cleanup] Manager initialized');
    }

    // Clean up body overflow-hidden leakage
    setupBodyStateCleanup() {
        let overflowTimer = null;
        
        const cleanupOverflow = () => {
            // Only remove overflow-hidden if no modals are open
            const hasOpenModals = document.querySelector('[x-show*="Modal"][x-show="true"], .modal-open, [data-modal-open="true"]');
            const hasGlobalOverlays = document.querySelector('.global-overlay.active, .loading-overlay.active');
            
            if (!hasOpenModals && !hasGlobalOverlays) {
                document.body.classList.remove('overflow-hidden');
                document.documentElement.classList.remove('overflow-hidden');
            }
        };

        // Debounced cleanup
        const debouncedCleanup = () => {
            if (overflowTimer) clearTimeout(overflowTimer);
            overflowTimer = setTimeout(cleanupOverflow, 100);
        };

        // Listen for various events that might leave body in dirty state
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                cleanupOverflow();
                this.cleanupFloatingElements();
            }
        });

        document.addEventListener('click', debouncedCleanup);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                setTimeout(cleanupOverflow, 50);
            }
        });

        // Watch for page unload
        window.addEventListener('beforeunload', () => {
            this.cleanupAll();
        });
    }

    // Setup global cleanup routines
    setupGlobalCleanup() {
        // Clean up orphaned loading states
        this.cleanupOrphanedLoadingStates();
        
        // Clean up zombie modals
        this.cleanupZombieModals();
        
        // Set up periodic cleanup
        setInterval(() => {
            this.cleanupMemoryLeaks();
            this.cleanupStaleCache();
        }, 30000); // Every 30 seconds
    }

    // Clean up orphaned loading states
    cleanupOrphanedLoadingStates() {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                // Remove loading states that have been active too long
                const longRunningLoaders = document.querySelectorAll('.loading-active[data-start-time]');
                const now = Date.now();
                
                longRunningLoaders.forEach(loader => {
                    const startTime = parseInt(loader.dataset.startTime);
                    if (now - startTime > 10000) { // 10 seconds max
                        loader.classList.remove('loading-active');
                        console.warn('[Cleanup] Removed orphaned loading state:', loader);
                    }
                });
            }, 1000);
        });
    }

    // Clean up zombie modals that might be stuck
    cleanupZombieModals() {
        const checkForZombieModals = () => {
            const modals = document.querySelectorAll('[x-show*="Modal"], [data-modal-state]');
            
            modals.forEach(modal => {
                const isVisible = modal.style.display !== 'none' && 
                                 !modal.classList.contains('hidden') &&
                                 modal.offsetWidth > 0 && 
                                 modal.offsetHeight > 0;
                
                const shouldBeClosed = modal.classList.contains('modal-closed') ||
                                      modal.getAttribute('aria-hidden') === 'true';
                
                if (shouldBeClosed && isVisible) {
                    console.warn('[Cleanup] Found zombie modal, cleaning up:', modal);
                    modal.style.display = 'none';
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
        };

        // Check every 5 seconds
        setInterval(checkForZombieModals, 5000);
        
        // Also check when DOM mutations occur
        if (window.MutationObserver) {
            const observer = new MutationObserver((mutations) => {
                let shouldCheck = false;
                
                mutations.forEach(mutation => {
                    if (mutation.type === 'attributes' || mutation.type === 'childList') {
                        shouldCheck = true;
                    }
                });
                
                if (shouldCheck) {
                    setTimeout(checkForZombieModals, 100);
                }
            });
            
            observer.observe(document.body, {
                attributes: true,
                childList: true,
                subtree: true,
                attributeFilter: ['class', 'style', 'aria-hidden', 'x-show']
            });
            
            this.observers.set('zombie-modal-observer', observer);
        }
    }

    // Clean up floating elements
    cleanupFloatingElements() {
        // Remove orphaned spinners
        const orphanedSpinners = document.querySelectorAll('.abs-spinner:not(:has(*)), .panel-spinner:not(:has(*))');
        orphanedSpinners.forEach(spinner => spinner.remove());
        
        // Remove orphaned overlays
        const orphanedOverlays = document.querySelectorAll('.global-overlay:not(.active), .loading-overlay:not(.active)');
        orphanedOverlays.forEach(overlay => overlay.remove());
        
        // Clean up ghost buttons (clicks that left elements in weird state)
        const ghostButtons = document.querySelectorAll('button[disabled]:not([data-intentionally-disabled])');
        if (ghostButtons.length > 5) { // Too many disabled buttons might indicate stuck state
            console.warn('[Cleanup] Found many disabled buttons, checking for stuck state');
            setTimeout(() => {
                ghostButtons.forEach(button => {
                    if (!button.classList.contains('loading')) {
                        button.disabled = false;
                    }
                });
            }, 1000);
        }
    }

    // Clean up memory leaks
    cleanupMemoryLeaks() {
        // Clean up old event listeners on removed elements
        this.cleanupDeadEventListeners();
        
        // Clean up abandoned fetch controllers
        if (window.AbortController && window.__activeControllers) {
            const staleControllers = Object.entries(window.__activeControllers)
                .filter(([key, controller]) => controller.signal?.aborted || !controller.signal)
                .map(([key]) => key);
            
            staleControllers.forEach(key => {
                delete window.__activeControllers[key];
            });
        }
        
        // Clean up old intervals/timeouts
        if (window.__activeTimeouts) {
            Object.entries(window.__activeTimeouts).forEach(([key, timeout]) => {
                if (timeout._elapsedTime && timeout._elapsedTime > 30000) { // 30s old
                    clearTimeout(timeout.id);
                    delete window.__activeTimeouts[key];
                }
            });
        }
    }

    // Clean up dead event listeners
    cleanupDeadEventListeners() {
        // This would require tracking event listeners, which is complex
        // For now, we'll clean up by removing orphaned elements
        const orphanedElements = document.querySelectorAll('[data-cleaned="true"]');
        orphanedElements.forEach(el => {
            if (!el.isConnected || el.parentNode === null) {
                el.remove();
            }
        });
    }

    // Clean up stale cache
    cleanupStaleCache() {
        if (window.SWRCache) {
            // Clean items older than 5 minutes
            const staleThreshold = Date.now() - (5 * 60 * 1000);
            
            try {
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith('swr:')) {
                        const cached = JSON.parse(localStorage.getItem(key));
                        if (cached && cached.timestamp < staleThreshold) {
                            localStorage.removeItem(key);
                            console.log('[Cleanup] Removed stale cache:', key);
                        }
                    }
                }
            } catch (error) {
                console.warn('[Cleanup] Error cleaning stale cache:', error);
            }
        }
    }

    // Manual cleanup methods
    cleanupAll() {
        console.log('[Cleanup] Performing full cleanup');
        
        // Clean up all observers
        this.observers.forEach(observer => observer.disconnect());
        this.observers.clear();
        
        // Clean up all timers
        this.timers.forEach(timer => clearTimeout(timer));
        this.timers.clear();
        
        // Clean up body state
        document.body.classList.remove('overflow-hidden', 'modal-open', 'loading-active');
        document.documentElement.classList.remove('overflow-hidden');
        
        // Clean up floating elements
        this.cleanupFloatingElements();
        
        // Clean up memory
        this.cleanupMemoryLeaks();
    }

    // Subscribe to cleanup events
    subscribe(eventType, handler) {
        const eventHandler = (event) => {
            if (event.detail && event.detail.type === eventType) {
                handler(event.detail.data);
            }
        };
        
        document.addEventListener('cleanup:' + eventType, eventHandler);
        
        // Return unsubscribe function
        return () => {
            document.removeEventListener('cleanup:' + eventType, eventHandler);
        };
    }

    // Trigger cleanup event
    trigger(eventType, data = {}) {
        document.dispatchEvent(new CustomEvent('cleanup:' + eventType, {
            detail: { type: eventType, data }
        }));
    }

    // Force cleanup of specific elements
    forceCleanup(selector, customAction = null) {
        const elements = document.querySelectorAll(selector);
        
        elements.forEach(element => {
            if (customAction) {
                customAction(element);
            } else {
                element.remove();
            }
        });
        
        return elements.length;
    }
}

// Global instance
const cleanup = new CleanupManager();

// Utility functions
window. forceModalCleanup() {
    return cleanup.forceCleanup('[x-show*="Modal"]', (modal) => {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    });
}

window. forceLoadingCleanup() {
    return cleanup.forceCleanup('.loading-active, .abs-spinner', (element) => {
        element.classList.remove('loading-active');
        if (element.classList.contains('abs-spinner')) {
            element.remove();
        }
    });
}

window. cleanupBodyState() {
    document.body.classList.remove('overflow-hidden', 'modal-open');
    document.documentElement.classList.remove('overflow-hidden');
}

window. subscribeCleanup(eventType, handler) {
    return cleanup.subscribe(eventType, handler);
}

// Export for global access
window.CleanupManager = cleanup;
window.forceModalCleanup = forceModalCleanup;
window.forceLoadingCleanup = forceLoadingCleanup;
window.cleanupBodyState = cleanupBodyState;

console.log('[Cleanup] Module loaded');
