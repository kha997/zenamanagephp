// Progress Bar Manager - Lightweight NProgress alternative
class ProgressManager {
    constructor() {
        this.isActive = false;
        this.progress = 0;
        this.animationFrame = null;
        this.timer = null;
        
        this.createProgressElement();
        this.setupEventListeners();
    }

    createProgressElement() {
        // Remove existing progress bar if any
        const existing = document.getElementById('page-progress');
        if (existing) {
            existing.remove();
        }

        // Create progress bar element
        const progressBar = document.createElement('div');
        progressBar.id = 'page-progress';
        progressBar.innerHTML = `
            <div class="progress-bar"></div>
            <div class="progress-spinner"></div>
        `;
        
        // Append to body
        document.body.appendChild(progressBar);
    }

    setupEventListeners() {
        // Listen for hard navigation start/end
        document.addEventListener('DOMContentLoaded', () => {
            this.setupHardNavigationDetection();
        });

        // Listen for visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pause();
            } else {
                this.resume();
            }
        });
    }

    setupHardNavigationDetection() {
        // Track URL changes for hard navigation
        let currentUrl = window.location.href;
        
        const detectNavigation = () => {
            if (window.location.href !== currentUrl) {
                this.start();
                currentUrl = window.location.href;
                
                // Auto-end after timeout
                setTimeout(() => this.done(), 5000);
            }
        };

        // Monitor URL changes
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;

        history.pushState = function(...args) {
            originalPushState.apply(this, args);
            setTimeout(detectNavigation, 0);
        };

        history.replaceState = function(...args) {
            originalReplaceState.apply(this, args);
            setTimeout(detectNavigation, 0);
        };

        window.addEventListener('popstate', detectNavigation);
    }

    start() {
        if (this.isActive) return;
        
        const progressBar = document.getElementById('page-progress');
        if (!progressBar) return;

        this.isActive = true;
        this.progress = 0;
        
        progressBar.classList.add('active');
        
        // Animation loop
        const animate = () => {
            if (!this.isActive) return;
            
            this.progress += Math.random() * 0.1;
            this.progress = Math.min(this.progress, 0.95); // Never reach 100% automatically
            
            progressBar.style.setProperty('--progress', `${this.progress * 100}%`);
            
            this.animationFrame = requestAnimationFrame(animate);
        };
        
        animate();
        
        console.log('[Progress] Started');
        
        // Dispatch event
        document.dispatchEvent(new CustomEvent('progress:start'));
    }

    set(percentage) {
        this.progress = Math.min(Math.max(percentage, 0), 1);
        
        const progressBar = document.getElementById('page-progress');
        if (progressBar) {
            progressBar.style.setProperty('--progress', `${this.progress * 100}%`);
        }
    }

    increment(amount = 0.1) {
        this.set(this.progress + amount);
    }

    done() {
        if (!this.isActive) return;
        
        const progressBar = document.getElementById('page-progress');
        if (!progressBar) return;

        // Set to 100% quickly
        this.progress = 1;
        progressBar.style.setProperty('--progress', '100%');
        
        // Remove bar after animation
        setTimeout(() => {
            if (progressBar) {
                progressBar.classList.remove('active');
                progressBar.classList.add('done');
                
                setTimeout(() => {
                    progressBar.classList.remove('done');
                }, 300);
            }
            
            this.isActive = false;
            this.progress = 0;
            
            if (this.timer) {
                clearTimeout(this.timer);
                this.timer = null;
            }
        }, 100);

        console.log('[Progress] Done');
        
        // Dispatch event
        document.dispatchEvent(new CustomEvent('progress:done'));
    }

    pause() {
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
            this.animationFrame = null;
        }
        
        console.log('[Progress] Paused');
    }

    resume() {
        if (this.isActive && !this.animationFrame) {
            const animate = () => {
                if (!this.isActive) return;
                
                this.progress += Math.random() * 0.05;
                this.progress = Math.min(this.progress, 0.95);
                
                const progressBar = document.getElementById('page-progress');
                if (progressBar) {
                    progressBar.style.setProperty('--progress', `${this.progress * 100}%`);
                }
                
                this.animationFrame = requestAnimationFrame(animate);
            };
            
            animate();
        }
        
        console.log('[Progress] Resumed');
    }

    // Check if progress is active
    isRunning() {
        return this.isActive;
    }

    // Get current progress
    getProgress() {
        return this.progress;
    }
}

// Global instance
const progress = new ProgressManager();

// Convenience functions
export function startProgress() {
    return progress.start();
}

export function setProgress(percentage) {
    return progress.set(percentage);
}

export function incrementProgress(amount) {
    return progress.increment(amount);
}

export function doneProgress() {
    return progress.done();
}

// Auto-start progress for fetch requests with special header
export function enableAutoProgress(enable = true) {
    if (enable) {
        const originalFetch = window.fetch;
        
        window.fetch = function(...args) {
            const [url, options = {}] = args;
            
            // Check if request should show progress
            const showProgress = options.showProgress !== false && 
                (options.headers?.['X-Show-Progress'] === 'true' || 
                 url.includes('/admin/') || 
                 args.length === 1); // Simple GET request
            
            if (showProgress && !progress.isRunning()) {
                progress.start();
                
                return originalFetch(...args).finally(() => {
                    setTimeout(() => progress.done(), 100);
                });
            }
            
            return originalFetch(...args);
        };
        
        console.log('[Progress] Auto-progress enabled');
    }
}

// Export for global access
window.ProgressManager = progress;
window.startProgress = startProgress;
window.setProgress = setProgress;
window.incrementProgress = incrementProgress;
window.doneProgress = doneProgress;
window.enableAutoProgress = enableAutoProgress;

// Auto-enable
enableAutoProgress(true);

console.log('[Progress] Manager initialized');
