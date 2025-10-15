/**
 * Progress Bar Manager - NProgress Integration for Hard Navigation Only
 */

class ProgressManager {
    constructor() {
        this.isEnabled = true;
        this.lastStartTime = 0;
        this.minDuration = 200; // Minimum progress duration for perceived performance
    }

    init() {
        // Only initialize if NProgress is available
        if (typeof window.NProgress === 'undefined') {
            console.warn('[Progress] NProgress not found - progress indicators disabled');
            return;
        }

        this.setupConfiguration();
        this.interceptNavigationEvents();
        this.setupSoftRefreshIntegration();
        
        console.log('[Progress] Progress manager initialized');
    }

    setupConfiguration() {
        // Configure NProgress
        window.NProgress.configure({
            showSpinner: false,
            minimum: 0.1,
            easing: 'ease',
            speed: 500,
            trickle: true,
            trickleSpeed: 200
        });
    }

    interruptNavigationEvents() {
        // Track programmatic navigation starts
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;

        history.pushState = (...args) => {
            this.handleHardNavigation();
            return originalPushState.apply(history, args);
        };
        
        history.replaceState = (...args) => {
            this.handleHardNavigation();
            return originalReplaceState.apply(history, args);
        };

        // Listen for form submissions
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form.tagName === 'FORM' && !this.isSoftAction(form)) {
                this.setProgress();
            }
        });
    }

    setupSoftRefreshIntegration() {
        // Listen for soft refresh events to avoid showing progress
        document.addEventListener('soft-refresh:triggered', () => {
            this.suppressProgressBar();
        });

        document.addEventListener('soft-refresh:completed', () => {
            this.suppressProgressBar();
        });

        // Listen for panel loads to avoid progress during soft refresh
        document.addEventListener('panel:dataUpdated', () => {
            this.checkSoftRefreshState();
        });
    }

    handleHardNavigation() {
        if (this.isSoftRefreshInProgress()) {
            return; // Skip progress for soft refresh
        }
        
        this.setProgress();
    }

    setProgress() {
        if (!this.isEnabled) return;
        
        const now = Date.now();
        
        // Ensure minimum duration for perceived performance
        if (now - this.lastStartTime < this.minDuration) {
            setTimeout(() => {
                window.NProgress.start();
            }, this.minDuration - (now - this.lastStartTime));
        } else {
            window.NProgress.start();
        }
        
        this.lastStartTime = now;
    }

    finishProgress() {
        if (window.NProgress) {
            window.NProgress.done();
        }
    }

    suppressProgressBar() {
        // Prevent NProgress from showing during soft refresh
        if (window.NProgress) {
            window.NProgress.configure({ minimum: 0 });
            setTimeout(() => {
                window.NProgress.configure({ minimum: 0.1 });
            }, 100);
        }
    }

    isSoftRefreshInProgress() {
        // Check if any panels are in soft refresh state
        const dimmedPanels = document.querySelectorAll('.soft-dim');
        return dimmedPanels.length > 0;
    }

    isSoftAction(element) {
        // Check if element should trigger soft action only
        return element.hasAttribute('data-soft-refresh') || 
               element.closest('[data-soft-refresh]');
    }

    checkSoftRefreshState() {
        // Monitor soft refresh completion
        if (this.isSoftRefreshInProgress()) {
            // Force finish any active progress
            this.finishProgress();
        }
    }

    // Public API
    enable() {
        this.isEnabled = true;
    }

    disable() {
        this.isEnabled = false;
        this.finishProgress();
    }

    configure(options = {}) {
        if (window.NProgress) {
            window.NProgress.configure(options);
        }
    }
}

// Initialize progress manager
const progressManager = new ProgressManager();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => progressManager.init());
} else {
    progressManager.init();
}

// Expose globally
window.ProgressManager = progressManager;

console.log('[Progress] Module loaded');