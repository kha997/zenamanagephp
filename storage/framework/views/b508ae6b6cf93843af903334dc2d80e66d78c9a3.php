

<?php
    $kpi_key = $kpi_key ?? 'default';
    $label = $label ?? 'KPI Label';
    $value = $value ?? '—';
    $trend = $trend ?? '+0%';
    $trend_type = $trend_type ?? 'neutral';
    $icon = $icon ?? 'fas fa-chart-line';
    $icon_color = $icon_color ?? 'blue';
    $primary_action = $primary_action ?? null;
    $secondary_action = $secondary_action ?? null;
    
    // Color mapping for icons and trends
    $color_classes = [
        'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
        'green' => ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
        'red' => ['bg' => 'bg-red-100', 'text' => 'text-red-600'],
        'yellow' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-600'],
        'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
        'indigo' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600'],
        'pink' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-600'],
        'gray' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600']
    ];
    
    $trend_classes = [
        'positive' => 'text-green-600',
        'negative' => 'text-red-600',
        'neutral' => 'text-gray-600'
    ];
    
    $icon_bg = $color_classes[$icon_color]['bg'] ?? $color_classes['blue']['bg'];
    $icon_text = $color_classes[$icon_color]['text'] ?? $color_classes['blue']['text'];
    $trend_class = $trend_classes[$trend_type] ?? $trend_classes['neutral'];
?>

<div class="kpi-card bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300" 
     data-kpi="<?php echo e($kpi_key); ?>"
     role="region" 
     aria-label="<?php echo e($label); ?> KPI Card">
    
    <!-- KPI Header -->
    <div class="kpi-header flex items-center mb-4">
        <div class="kpi-icon w-12 h-12 <?php echo e($icon_bg); ?> rounded-lg flex items-center justify-center mr-4">
            <i class="<?php echo e($icon); ?> <?php echo e($icon_text); ?> text-xl" aria-hidden="true"></i>
        </div>
        <div class="kpi-info flex-1">
            <h3 class="kpi-label text-sm font-medium text-gray-600 mb-1"><?php echo e($label); ?></h3>
            <p class="kpi-value text-2xl font-bold text-gray-900 kpi--<?php echo e($kpi_key); ?>" 
               aria-label="<?php echo e($label); ?> value">
                <?php echo e($value); ?>

            </p>
            <p class="kpi-trend text-xs <?php echo e($trend_class); ?>" 
               aria-label="Trend: <?php echo e($trend); ?>">
                <?php echo e($trend); ?>

            </p>
        </div>
    </div>
    
    <!-- KPI Actions -->
    <?php if($primary_action || $secondary_action): ?>
    <div class="kpi-actions flex items-center space-x-2">
        <?php if($primary_action): ?>
            <a href="<?php echo e($primary_action['url']); ?>" 
               class="btn btn-primary btn-sm flex-1 text-center"
               data-action="primary"
               aria-label="<?php echo e($primary_action['label']); ?> for <?php echo e($label); ?>">
                <i class="fas fa-arrow-right mr-2" aria-hidden="true"></i>
                <?php echo e($primary_action['label']); ?>

            </a>
        <?php endif; ?>
        
        <?php if($secondary_action): ?>
            <a href="<?php echo e($secondary_action['url']); ?>" 
               class="btn btn-secondary btn-sm"
               data-action="secondary"
               aria-label="<?php echo e($secondary_action['label']); ?> for <?php echo e($label); ?>">
                <i class="fas fa-plus" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Loading State (Hidden by default) -->
    <div class="kpi-loading hidden absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
        <div class="flex items-center space-x-2 text-gray-600">
            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
            <span class="text-sm">Loading...</span>
        </div>
    </div>
    
    <!-- Error State (Hidden by default) -->
    <div class="kpi-error hidden absolute inset-0 bg-red-50 flex items-center justify-center rounded-lg">
        <div class="text-center">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl mb-2" aria-hidden="true"></i>
            <p class="text-sm text-red-600">Failed to load data</p>
            <button class="btn btn-sm btn-outline-red mt-2" data-action="retry">
                Try Again
            </button>
        </div>
    </div>
</div>

<?php $__env->startPush('styles'); ?>
<style>
    .kpi-card {
        position: relative;
        min-height: 140px;
    }
    
    .kpi-card:hover {
        transform: translateY(-2px);
    }
    
    .kpi-card:focus-within {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .kpi-card {
            min-height: 120px;
        }
        
        .kpi-header {
            flex-direction: column;
            text-align: center;
        }
        
        .kpi-icon {
            margin-right: 0;
            margin-bottom: 1rem;
        }
        
        .kpi-actions {
            flex-direction: column;
            space-x: 0;
            gap: 0.5rem;
        }
        
        .kpi-actions .btn {
            width: 100%;
        }
    }
    
    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .kpi-card {
            border: 2px solid #000;
        }
        
        .kpi-value {
            font-weight: 900;
        }
    }
    
    /* Reduced motion support */
    @media (prefers-reduced-motion: reduce) {
        .kpi-card {
            transition: none;
        }
        
        .kpi-card:hover {
            transform: none;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // KPI Card JavaScript functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize KPI cards
        const kpiCards = document.querySelectorAll('.kpi-card[data-kpi]');
        
        kpiCards.forEach(card => {
            const kpiKey = card.dataset.kpi;
            
            // Add click tracking
            card.addEventListener('click', function(e) {
                if (!e.target.closest('a, button')) {
                    // Track KPI card clicks for analytics
                    if (window.gtag) {
                        gtag('event', 'kpi_card_click', {
                            'kpi_key': kpiKey,
                            'kpi_label': card.querySelector('.kpi-label').textContent
                        });
                    }
                }
            });
            
            // Add keyboard navigation
            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const primaryAction = card.querySelector('[data-action="primary"]');
                    if (primaryAction) {
                        primaryAction.click();
                    }
                }
            });
            
            // Make card focusable
            card.setAttribute('tabindex', '0');
        });
        
        // Retry functionality for error states
        document.addEventListener('click', function(e) {
            if (e.target.matches('[data-action="retry"]')) {
                const card = e.target.closest('.kpi-card');
                if (card) {
                    retryKPI(card);
                }
            }
        });
    });
    
    // Function to show loading state
    function showKPILoading(kpiKey) {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            card.querySelector('.kpi-loading').classList.remove('hidden');
            card.querySelector('.kpi-error').classList.add('hidden');
        }
    }
    
    // Function to show error state
    function showKPIError(kpiKey, errorMessage = 'Failed to load data') {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            card.querySelector('.kpi-loading').classList.add('hidden');
            card.querySelector('.kpi-error').classList.remove('hidden');
            card.querySelector('.kpi-error p').textContent = errorMessage;
        }
    }
    
    // Function to hide loading/error states
    function hideKPIStates(kpiKey) {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            card.querySelector('.kpi-loading').classList.add('hidden');
            card.querySelector('.kpi-error').classList.add('hidden');
        }
    }
    
    // Function to retry KPI loading
    function retryKPI(card) {
        const kpiKey = card.dataset.kpi;
        showKPILoading(kpiKey);
        
        // Trigger KPI refresh
        if (window.refreshKPI) {
            window.refreshKPI(kpiKey);
        }
    }
    
    // Function to update KPI value
    function updateKPIValue(kpiKey, value, trend = null, trendType = 'neutral') {
        const card = document.querySelector(`[data-kpi="${kpiKey}"]`);
        if (card) {
            const valueElement = card.querySelector('.kpi-value');
            const trendElement = card.querySelector('.kpi-trend');
            
            if (valueElement) {
                valueElement.textContent = value;
            }
            
            if (trendElement && trend) {
                trendElement.textContent = trend;
                trendElement.className = `kpi-trend text-xs ${getTrendClass(trendType)}`;
            }
            
            hideKPIStates(kpiKey);
        }
    }
    
    // Helper function to get trend class
    function getTrendClass(trendType) {
        const trendClasses = {
            'positive': 'text-green-600',
            'negative': 'text-red-600',
            'neutral': 'text-gray-600'
        };
        return trendClasses[trendType] || trendClasses['neutral'];
    }
    
    // Export functions for global use
    window.showKPILoading = showKPILoading;
    window.showKPIError = showKPIError;
    window.hideKPIStates = hideKPIStates;
    window.updateKPIValue = updateKPIValue;
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/dashboard/charts/dashboard-kpi-card.blade.php ENDPATH**/ ?>