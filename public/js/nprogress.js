// NProgress-like Implementation for Admin Pages
class ProgressBar {
    constructor() {
        this.status = null;
        this.mounted = false;
        this.trickleUp = 0;
        
        this.conf = {
            minimum: 0.08,
            trickleSpeed: 200,
            initial: 0.3,
            easing: 'ease',
            speed: 200,
            trickle: true
        };
        
        this.init();
    }

    init() {
        const settings = Object.assign({}, this.conf);

        // Create progress bar DOM
        this.element = document.querySelector('#nprogress') || this.createElement();
        
        // Initialize status
        this.status = document.querySelector('#nprogress .bar') || this.element.querySelector('.bar');
        
        if (this.status) {
            this.status.style.transition = `all ${settings.speed}ms ${settings.easing}`;
            this.status.style.opacity = 1;
            this.element.style.opacity = 0;
        }

        // Create spinner
        this.spinner = document.querySelector('#nprogress .spinner') || this.createElementSpinner();
        
        // Hide initially
        this.hide();
    }

    createElement() {
        const element = document.createElement('div');
        element.id = 'nprogress';
        element.innerHTML = '<div class="bar"></div>';
        
        document.body.appendChild(element);
        return element;
    }

    createElementSpinner() {
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        this.element.appendChild(spinner);
        return spinner;
    }

    show() {
        if (!this.status) this.init();
        
        this.percentage(0);
        this.mounted = true;
        this.element.style.opacity = 1;
        
        // Start trickling up
        if (this.conf.trickle) {
            this.trickle();
        }
        
        // Set to initial status
        this.percentage(this.conf.initial);
    }

    start() {
        this.show();
        return this;
    }

    done() {
        this.hide();
        return this;
    }

    set(n) {
        this.percentage(n);
        return this;
    }

    inc(amount) {
        let n = this.status ? parseFloat(this.status.getAttribute('data-transition-amount') || '0') : 0;
        n += amount || this.random();
        this.set(n);
        return this;
    }

    trickle() {
        if (!this.mounted) return;
        
        this.inc(this.random());
        if (this.trickleUp) {
            clearTimeout(this.trickleUp);
        }
        
        this.trickleUp = setTimeout(() => this.trickle(), this.conf.trickleSpeed);
    }

    percentage(n) {
        if (!this.status) return this;
        
        this.status.setAttribute('data-transition-amount', n);
        this.status.style.transform = `translate3d(${this.toBarPerc(n)}%, 0, 0)`;
    }

    toBarPerc(n) {
        return Math.max(-35, Math.min(100, (n - this.conf.minimum) / (100 - this.conf.minimum) * 100));
    }

    random() {
        return Math.random() * 0.1 + 0.05;
    }

    hide() {
        if (!this.mounted) return;
        
        this.element.style.opacity = 0;
        this.mounted = false;
        
        // Clear trickle timer
        if (this.trickleUp) {
            clearTimeout(this.trickleUp);
        }
        
        // Hide spinner
        if (this.spinner) {
            this.spinner.style.opacity = 0;
            setTimeout(() => {
                if (this.spinner) {
                    this.spinner.style.display = 'none';
                }
            }, 500);
        }
    }

    // Configure settings
    configure(options) {
        Object.assign(this.conf, options);
    }
}

// Global instance
const NProgress = new ProgressBar();

// Fetch wrapper with automatic progress bar
const originalFetch = window.fetch;
window.fetch = function(...args) {
    NProgress.start();
    
    const abortController = new AbortController();
    
    const fetchPromise = originalFetch(...args).finally(() => {
        NProgress.done();
    });
    
    // Allow aborting
    fetchPromise.abort = () => abortController.abort();
    fetchPromise.signal = abortController.signal;
    
    return fetchPromise;
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NProgress;
}

// Global access
window.NProgress = NProgress;

console.log('NProgress initialized');
