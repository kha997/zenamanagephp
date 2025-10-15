/**
 * StatCard Component
 * Reusable KPI card component matching Users page design
 * Usage: window.statCard(props) or Alpine.js integration
 */

class StatCard {
    constructor(props) {
        this.props = {
            title: '',
            value: 0,
            delta: null,
            deltaType: 'neutral',
            suffix: '',
            icon: '',
            variant: 'gray',
            sparkline: null,
            linkHref: null,
            loading: false,
            tooltip: '',
            ariaLabel: '',
            ...props
        };
    }

    getVariantClasses() {
        const variants = {
            blue: 'bg-blue-50 text-blue-700 border-blue-100',
            red: 'bg-red-50 text-red-700 border-red-100',
            amber: 'bg-amber-50 text-amber-700 border-amber-100',
            indigo: 'bg-indigo-50 text-indigo-700 border-indigo-100',
            orange: 'bg-orange-50 text-orange-700 border-orange-100',
            gray: 'bg-gray-50 text-gray-700 border-gray-100'
        };
        
        const baseClasses = 'border rounded-lg p-6 transition-all duration-200 hover:shadow-md';
        return `${baseClasses} ${variants[this.props.variant]}`;
    }

    getIconClasses() {
        const iconVariants = {
            blue: 'bg-blue-100 text-blue-600',
            red: 'bg-red-100 text-red-600',
            amber: 'bg-amber-100 text-amber-600',
            indigo: 'bg-indigo-100 text-indigo-600',
            orange: 'bg-orange-100 text-orange-600',
            gray: 'bg-gray-100 text-gray-600'
        };
        
        return `inline-flex items-center justify-center w-8 h-8 rounded-full ${iconVariants[this.props.variant]}`;
    }

    getDeltaClasses() {
        const deltaVariants = {
            up: 'bg-green-100 text-green-800',
            down: 'bg-red-100 text-red-800',
            neutral: 'bg-gray-100 text-gray-800'
        };
        
        return `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${deltaVariants[this.props.deltaType]}`;
    }

    getDeltaIcon() {
        if (this.props.deltaType === 'up') return 'fas fa-arrow-up';
        if (this.props.deltaType === 'down') return 'fas fa-arrow-down';
        return 'fas fa-minus';
    }

    getDeltaText() {
        if (this.props.delta === null || this.props.delta === undefined) return '';
        
        const delta = typeof this.props.delta === 'number' ? this.props.delta : parseFloat(this.props.delta);
        if (Math.abs(delta) < 0.1) return '0%';
        
        const sign = delta > 0 ? '+' : '';
        if (this.props.suffix === '%') {
            return `${sign}${delta.toFixed(1)}%`;
        }
        return `${sign}${delta.toLocaleString()}`;
    }

    getSparklineSVG() {
        if (!this.props.sparkline || !Array.isArray(this.props.sparkline) || this.props.sparkline.length < 2) {
            return '';
        }
        
        const width = 120;
        const height = 32;
        const padding = 4;
        
        // Normalize sparkline data
        const data = this.props.sparkline;
        const min = Math.min(...data);
        const max = Math.max(...data);
        const range = max - min || 1;
        
        // Generate SVG path
        const points = data.map((value, index) => {
            const x = padding + (index / (data.length - 1)) * (width<｜tool▁call▁begin｜> 2 * padding);
            const y = height - padding - ((value - min) / range) * (height - 2 * padding);
            return `${x},${y}`;
        });
        
        const pathData = `M ${points.join(' L ')}`;
        
        return `
            <svg width="${width}" height="${height}" class="stroke-current opacity-60" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
                <path d="${pathData}" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;
    }

    render() {
        const containerClass = this.getVariantClasses();
        const iconClass = this.getIconClasses();
        const deltaClass = this.getDeltaClasses();
        const sparklineSVG = this.getSparklineSVG();
        
        // Loading skeleton or actual content
        const content = this.props.loading ? this.renderSkeleton() : this.renderContent();
        
        return `
            <div class="${containerClass}" 
                 ${this.props.ariaLabel ? `aria-label="${this.props.ariaLabel}"` : ''}
                 ${this.props.tooltip ? `title="${this.props.tooltip}"` : ''}
                 ${this.props.linkHref ? `onclick="window.location.href='${this.props.linkHref}'" style="cursor: pointer;"` : ''}>
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <div class="${iconClass} mr-3" ${this.props.icon ? `style="background-image: url('data:image/svg+xml;base64,${btoa(this.getIconSVG())}')"` : ''}>
                                <i class="fas fa-${this.props.icon} text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium leading-none mb-1">${this.props.title}</h3>
                                <div class="flex items-baseline space-x-2">
                                    <span class="text-2xl font-bold tabular-nums" 
                                          aria-live="polite">
                                        ${this.props.loading ? '--' : this.props.value}
                                    </span>
                                    <span class="text-sm font-normal opacity-80">
                                        ${this.props.suffix || ''}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        ${this.props.delta !== null && !this.props.loading ? `
                            <div class="mt-2">
                                <span class="${deltaClass}">
                                    <i class="${this.getDeltaIcon()} mr-1"></i>
                                    ${this.getDeltaText()}
                                </span>
                            </div>
                        ` : ''}
                        
                        ${sparklineSVG && !this.props.loading ? `
                            <div class="mt-3">
                                <div class="min-h-[32px] flex items-center">
                                    ${sparklineSVG}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    
                    ${content}
                </div>
            </div>
        `;
    }

    renderSkeleton() {
        return `
            <div class="flex items-center space-x-3 animate-pulse">
                <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
                <div class="flex-1">
                    <div class="h-3 bg-gray-200 rounded w-20 mb-2"></div>
                    <div class="h-6 bg-gray-200 rounded w-16 mb-1"></div>
                    <div class="h-3 bg-gray-200 rounded w-12"></div>
                </div>
            </div>
        `;
    }

    renderContent() {
        return '';
    }

    getIconSVG() {
        // Simple icon mappings - could be expanded
        const iconMap = {
            'shield-check': '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'triangle-alert': '<svg fill="currentColor" viewBox="0 0 20 20"><path d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z"/></svg>',
            'lock': '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>',
            'activity': '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg>',
            'key': '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.74L8 16H6a1 1 0 01-1-1v-1.257L4.26 13.257A6 6 0 0118 8zm-6 4a4 4 0 100-8 4 4 0 000 8z"/></svg>'
        };
        return iconMap[this.props.icon] || iconMap['shield-check'];
    }
}

// Utility functions for Security page
window.StatCardUtils = {
    formatInt: (n) => {
        if (typeof n !== 'number') return '0';
        return n.toLocaleString();
    },
    
    formatPercent: (n) => {
        if (typeof n !== 'number') return '0.0';
        return n.toFixed(1);
    },
    
    deltaArrow: (value, policy = 'higher_is_better') => {
        if (value === 0) return 'neutral';
        const isHigher = value > 0;
        const isBetterPolicy = policy === 'higher_is_better' || policy === 'higher-is-better';
        return isHigher === isBetterPolicy ? 'up' : 'down';
    },
    
    // Helper for Security-specific delta policies
    getSecurityDeltaType: (deltaValue, kpiType) => {
        const policies = {
            'mfaAdoption': 'higher_is_better',
            'failedLogins': 'higher_is_worse', 
            'lockedAccounts': 'higher_is_worse',
            'activeSessions': 'higher_is_neutral',
            'riskyKeys': 'higher_is_worse'
        };
        
        return this.deltaArrow(deltaValue, policies[kpiType] || 'higher_is_better');
    }
};

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { StatCard: StatCard };
}
