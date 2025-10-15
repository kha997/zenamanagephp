


<div x-data="accessibilityDashboard()" class="accessibility-dashboard bg-white p-6 rounded-lg shadow-md border border-gray-200">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Accessibility Dashboard</h2>
        <div class="flex items-center space-x-2">
            <button 
                @click="refreshComplianceReport()"
                class="btn-secondary flex items-center gap-2"
                :disabled="loading"
            >
                <i class="fas fa-sync-alt" :class="{ 'animate-spin': loading }"></i>
                <span>Refresh Report</span>
            </button>
            <button 
                @click="exportReport()"
                class="btn-primary flex items-center gap-2"
            >
                <i class="fas fa-download"></i>
                <span>Export Report</span>
            </button>
        </div>
    </div>
    
    <!-- Compliance Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-green-900">WCAG Compliance</h3>
                    <p class="text-2xl font-bold text-green-700" x-text="complianceScore + '%'"></p>
                    <p class="text-sm text-green-600">Level AA</p>
                </div>
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-blue-900">Users with Preferences</h3>
                    <p class="text-2xl font-bold text-blue-700" x-text="stats.users_with_preferences"></p>
                    <p class="text-sm text-blue-600">Out of <span x-text="stats.total_users"></span> total</p>
                </div>
            </div>
        </div>
        
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-purple-900">Trend</h3>
                    <p class="text-2xl font-bold text-purple-700">+10%</p>
                    <p class="text-sm text-purple-600">This month</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Preferences -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">User Preferences</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <template x-for="(value, key) in userPreferences" :key="'pref-' + key">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <span class="text-sm font-medium text-gray-700" x-text="formatPreferenceName(key)"></span>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-500 mr-2" x-text="value ? 'On' : 'Off'"></span>
                        <div class="w-8 h-4 rounded-full transition-colors" :class="value ? 'bg-green-500' : 'bg-gray-300'">
                            <div class="w-3 h-3 bg-white rounded-full transition-transform" :class="value ? 'translate-x-4' : 'translate-x-0.5'"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Compliance Checks -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Compliance Checks</h3>
        <div class="space-y-3">
            <template x-for="(check, key) in complianceChecks" :key="'check-' + key">
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" :class="check.status === 'pass' ? 'bg-green-100' : 'bg-red-100'">
                            <i class="fas" :class="check.status === 'pass' ? 'fa-check text-green-600' : 'fa-times text-red-600'"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900" x-text="formatCheckName(key)"></h4>
                            <p class="text-sm text-gray-600" x-text="check.details"></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold" :class="check.status === 'pass' ? 'text-green-600' : 'text-red-600'" x-text="check.score + '%'"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Color Contrast Checker -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Color Contrast Checker</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="foreground-color" class="block text-sm font-medium text-gray-700 mb-2">Foreground Color</label>
                <input 
                    type="color" 
                    id="foreground-color" 
                    x-model="contrastTest.foreground"
                    @change="checkContrast()"
                    class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
            </div>
            <div>
                <label for="background-color" class="block text-sm font-medium text-gray-700 mb-2">Background Color</label>
                <input 
                    type="color" 
                    id="background-color" 
                    x-model="contrastTest.background"
                    @change="checkContrast()"
                    class="w-full h-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
            </div>
        </div>
        
        <div x-show="contrastResult" class="mt-4 p-4 rounded-lg" :class="contrastResult.compliant ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium" :class="contrastResult.compliant ? 'text-green-900' : 'text-red-900'">
                        Contrast Ratio: <span x-text="contrastResult.contrast_ratio"></span>
                    </h4>
                    <p class="text-sm" :class="contrastResult.compliant ? 'text-green-700' : 'text-red-700'" x-text="contrastResult.recommendation"></p>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium" :class="contrastResult.compliant ? 'text-green-600' : 'text-red-600'">
                        WCAG <span x-text="contrastTest.level"></span>
                    </div>
                    <div class="text-xs" :class="contrastResult.compliant ? 'text-green-500' : 'text-red-500'">
                        <span x-show="contrastResult.aa_compliant">AA ✓</span>
                        <span x-show="contrastResult.aaa_compliant">AAA ✓</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recommendations -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Recommendations</h3>
        <div class="space-y-2">
            <template x-for="(recommendation, index) in recommendations" :key="'rec-' + index">
                <div class="flex items-start space-x-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <i class="fas fa-lightbulb text-blue-600 mt-1"></i>
                    <p class="text-blue-800 text-sm" x-text="recommendation"></p>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Statistics Chart -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Usage Statistics</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-lg font-medium text-gray-800 mb-3">Most Used Features</h4>
                <div class="space-y-2">
                    <template x-for="(count, feature) in stats.most_used_features" :key="feature">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700" x-text="formatFeatureName(feature)"></span>
                            <div class="flex items-center">
                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-blue-600 h-2 rounded-full" :style="`width: ${(count / 100) * 100}%`"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-900" x-text="count + '%'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <div>
                <h4 class="text-lg font-medium text-gray-800 mb-3">User Distribution</h4>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">High Contrast Users</span>
                        <span class="text-sm font-medium text-gray-900" x-text="stats.high_contrast_users"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">Screen Reader Users</span>
                        <span class="text-sm font-medium text-gray-900" x-text="stats.screen_reader_users"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">Keyboard Only Users</span>
                        <span class="text-sm font-medium text-gray-900" x-text="stats.keyboard_only_users"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">Reduced Motion Users</span>
                        <span class="text-sm font-medium text-gray-900" x-text="stats.reduced_motion_users"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">Large Text Users</span>
                        <span class="text-sm font-medium text-gray-900" x-text="stats.large_text_users"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('accessibilityDashboard', () => ({
        loading: false,
        complianceScore: 95,
        userPreferences: {},
        complianceChecks: {},
        stats: {},
        recommendations: [],
        contrastTest: {
            foreground: '#000000',
            background: '#ffffff',
            level: 'AA'
        },
        contrastResult: null,
        
        init() {
            this.loadUserPreferences();
            this.loadComplianceReport();
            this.loadStatistics();
        },
        
        async loadUserPreferences() {
            try {
                const response = await fetch('<?php echo e(route('api.accessibility.preferences')); ?>');
                const data = await response.json();
                if (data.success) {
                    this.userPreferences = data.data;
                }
            } catch (error) {
                console.error('Error loading user preferences:', error);
            }
        },
        
        async loadComplianceReport() {
            try {
                const response = await fetch('<?php echo e(route('api.accessibility.compliance-report')); ?>');
                const data = await response.json();
                if (data.success) {
                    this.complianceScore = data.data.compliance_score;
                    this.complianceChecks = data.data.checks;
                    this.recommendations = data.data.recommendations;
                }
            } catch (error) {
                console.error('Error loading compliance report:', error);
            }
        },
        
        async loadStatistics() {
            try {
                const response = await fetch('<?php echo e(route('api.accessibility.statistics')); ?>');
                const data = await response.json();
                if (data.success) {
                    this.stats = data.data;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        },
        
        async refreshComplianceReport() {
            this.loading = true;
            await this.loadComplianceReport();
            this.loading = false;
        },
        
        async checkContrast() {
            try {
                const response = await fetch('<?php echo e(route('api.accessibility.check-color-contrast')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        foreground_color: this.contrastTest.foreground,
                        background_color: this.contrastTest.background,
                        level: this.contrastTest.level
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.contrastResult = data.data;
                }
            } catch (error) {
                console.error('Error checking contrast:', error);
            }
        },
        
        async exportReport() {
            try {
                const response = await fetch('<?php echo e(route('api.accessibility.generate-report')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ format: 'json' })
                });
                const data = await response.json();
                if (data.success) {
                    // Download the report
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.data.filename;
                    a.click();
                    window.URL.revokeObjectURL(url);
                }
            } catch (error) {
                console.error('Error exporting report:', error);
            }
        },
        
        formatPreferenceName(key) {
            const names = {
                'high_contrast': 'High Contrast',
                'reduced_motion': 'Reduced Motion',
                'large_text': 'Large Text',
                'keyboard_navigation': 'Keyboard Navigation',
                'screen_reader_optimized': 'Screen Reader Optimized',
                'focus_indicators': 'Focus Indicators',
                'skip_links': 'Skip Links',
                'aria_labels': 'ARIA Labels'
            };
            return names[key] || key;
        },
        
        formatCheckName(key) {
            const names = {
                'color_contrast': 'Color Contrast',
                'keyboard_navigation': 'Keyboard Navigation',
                'screen_reader': 'Screen Reader Support',
                'focus_management': 'Focus Management',
                'skip_links': 'Skip Links',
                'alt_text': 'Alt Text',
                'form_labels': 'Form Labels',
                'heading_structure': 'Heading Structure',
                'live_regions': 'Live Regions',
                'error_handling': 'Error Handling'
            };
            return names[key] || key;
        },
        
        formatFeatureName(feature) {
            const names = {
                'keyboard_navigation': 'Keyboard Navigation',
                'focus_indicators': 'Focus Indicators',
                'skip_links': 'Skip Links',
                'high_contrast': 'High Contrast',
                'large_text': 'Large Text',
                'screen_reader_optimized': 'Screen Reader Optimized'
            };
            return names[feature] || feature;
        }
    }));
});
</script>

<style>
/* Accessibility dashboard styles */
.accessibility-dashboard {
    font-family: system-ui, -apple-system, sans-serif;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .accessibility-dashboard button {
        border: 2px solid currentColor;
    }
    
    .accessibility-dashboard input {
        border: 2px solid currentColor;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .accessibility-dashboard * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Focus styles */
.accessibility-dashboard *:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Button styles */
.btn-primary {
    background: #2563eb;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.2s;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.2s;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-secondary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/a11y/accessibility-dashboard.blade.php ENDPATH**/ ?>