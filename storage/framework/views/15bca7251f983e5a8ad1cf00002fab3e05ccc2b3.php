

<?php
    $chartId = $chartId ?? 'interactive-chart';
    $title = $title ?? 'Interactive Chart';
    $type = $type ?? 'line';
    $data = $data ?? [];
    $drillDownEnabled = $drillDownEnabled ?? false;
    $exportEnabled = $exportEnabled ?? true;
    $realTimeEnabled = $realTimeEnabled ?? false;
    $height = $height ?? 400;
?>

<div class="bg-white rounded-lg shadow-lg p-6" 
     x-data="interactiveChart('<?php echo e($chartId); ?>', '<?php echo e($type); ?>', <?php echo \Illuminate\Support\Js::from($data)->toHtml() ?>)"
     x-init="init()">
    
    <!-- Chart Header -->
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900" x-text="title"><?php echo e($title); ?></h3>
        
        <div class="flex items-center space-x-2">
            <!-- Chart Type Toggle -->
            <div class="flex items-center space-x-1 bg-gray-100 rounded-lg p-1">
                <button @click="setChartType('line')" 
                        :class="chartType === 'line' ? 'bg-white shadow-sm' : 'text-gray-600'"
                        class="px-3 py-1 text-sm rounded-md transition-all">
                    <i class="fas fa-chart-line"></i>
                </button>
                <button @click="setChartType('bar')" 
                        :class="chartType === 'bar' ? 'bg-white shadow-sm' : 'text-gray-600'"
                        class="px-3 py-1 text-sm rounded-md transition-all">
                    <i class="fas fa-chart-bar"></i>
                </button>
                <button @click="setChartType('area')" 
                        :class="chartType === 'area' ? 'bg-white shadow-sm' : 'text-gray-600'"
                        class="px-3 py-1 text-sm rounded-md transition-all">
                    <i class="fas fa-chart-area"></i>
                </button>
            </div>
            
            <!-- Drill Down Button -->
            <?php if($drillDownEnabled): ?>
            <button @click="enableDrillDown()" 
                    :disabled="!canDrillDown"
                    class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors disabled:opacity-50">
                <i class="fas fa-search-plus mr-1"></i>Drill Down
            </button>
            <?php endif; ?>
            
            <!-- Export Button -->
            <?php if($exportEnabled): ?>
            <button @click="exportChart()" 
                    class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors">
                <i class="fas fa-download mr-1"></i>Export
            </button>
            <?php endif; ?>
            
            <!-- Real-time Toggle -->
            <?php if($realTimeEnabled): ?>
            <button @click="toggleRealTime()" 
                    :class="realTimeEnabled ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-3 py-1 text-sm rounded-lg transition-colors">
                <i class="fas fa-broadcast-tower mr-1" :class="realTimeEnabled ? 'animate-pulse' : ''"></i>
                <span x-text="realTimeEnabled ? 'Live' : 'Static'"></span>
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chart Container -->
    <div :id="chartId" class="w-full" :style="'height: <?php echo e($height); ?>px;'"></div>
    
    <!-- Chart Controls -->
    <div class="mt-4 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Time Range Selector -->
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700">Time Range:</label>
                <select @change="updateTimeRange()" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                    <option value="1y">Last year</option>
                </select>
            </div>
            
            <!-- Data Source Selector -->
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700">Data Source:</label>
                <select @change="updateDataSource()" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="revenue">Revenue</option>
                    <option value="projects">Projects</option>
                    <option value="tasks">Tasks</option>
                    <option value="team">Team</option>
                </select>
            </div>
        </div>
        
        <!-- Chart Info -->
        <div class="flex items-center space-x-4 text-sm text-gray-600">
            <span x-text="'Last updated: ' + lastUpdated"></span>
            <span x-text="'Data points: ' + dataPoints"></span>
        </div>
    </div>
    
    <!-- Loading State -->
    <div x-show="loading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
        <div class="flex items-center space-x-2 text-gray-600">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading chart data...</span>
        </div>
    </div>
    
    <!-- Error State -->
    <div x-show="error" class="absolute inset-0 bg-red-50 flex items-center justify-center rounded-lg">
        <div class="text-center">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl mb-2"></i>
            <p class="text-sm text-red-600" x-text="error"></p>
            <button @click="retry()" class="btn btn-sm btn-outline-red mt-2">
                Try Again
            </button>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function interactiveChart(chartId, type, data) {
    return {
        chartId: chartId,
        title: '<?php echo e($title); ?>',
        chartType: type,
        originalData: data,
        currentData: data,
        loading: false,
        error: null,
        canDrillDown: true,
        realTimeEnabled: <?php echo e($realTimeEnabled ? 'true' : 'false'); ?>,
        lastUpdated: new Date().toLocaleTimeString(),
        dataPoints: data.length || 0,
        chart: null,
        realTimeInterval: null,
        
        init() {
            console.log('📊 Initializing interactive chart:', this.chartId);
            this.initChart();
            
            if (this.realTimeEnabled) {
                this.startRealTimeUpdates();
            }
        },
        
        initChart() {
            if (typeof ApexCharts === 'undefined') {
                console.error('ApexCharts not loaded');
                this.error = 'Chart library not available';
                return;
            }
            
            const options = this.getChartOptions();
            
            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }
            
            this.chart = new ApexCharts(document.querySelector(`#${this.chartId}`), options);
            this.chart.render();
            
            // Add click event for drill-down
            if (<?php echo e($drillDownEnabled ? 'true' : 'false'); ?>) {
                this.chart.on('dataPointSelection', (event, chartContext, config) => {
                    this.handleDrillDown(config.dataPointIndex);
                });
            }
        },
        
        getChartOptions() {
            const baseOptions = {
                chart: {
                    type: this.chartType,
                    height: <?php echo e($height); ?>,
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: true,
                            zoom: true,
                            zoomin: true,
                            zoomout: true,
                            pan: true,
                            reset: true
                        }
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 800
                    }
                },
                series: this.getSeriesData(),
                xaxis: {
                    categories: this.getCategories()
                },
                yaxis: {
                    title: {
                        text: this.getYAxisTitle()
                    }
                },
                colors: this.getColors(),
                tooltip: {
                    y: {
                        formatter: this.getTooltipFormatter()
                    }
                },
                legend: {
                    position: 'bottom'
                }
            };
            
            // Add specific options based on chart type
            if (this.chartType === 'area') {
                baseOptions.fill = {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.7,
                        opacityTo: 0.9,
                        stops: [0, 90, 100]
                    }
                };
            }
            
            return baseOptions;
        },
        
        getSeriesData() {
            // Convert data to ApexCharts format
            if (Array.isArray(this.currentData)) {
                return [{
                    name: this.title,
                    data: this.currentData
                }];
            }
            
            return this.currentData;
        },
        
        getCategories() {
            // Generate categories based on time range
            const categories = [];
            const now = new Date();
            
            for (let i = 11; i >= 0; i--) {
                const date = new Date(now);
                date.setDate(date.getDate() - i);
                categories.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            
            return categories;
        },
        
        getYAxisTitle() {
            const titles = {
                'revenue': 'Revenue ($)',
                'projects': 'Projects',
                'tasks': 'Tasks',
                'team': 'Team Members'
            };
            
            return titles[this.dataSource] || 'Value';
        },
        
        getColors() {
            const colorSets = {
                'revenue': ['#3b82f6'],
                'projects': ['#10b981'],
                'tasks': ['#f59e0b'],
                'team': ['#8b5cf6']
            };
            
            return colorSets[this.dataSource] || ['#3b82f6'];
        },
        
        getTooltipFormatter() {
            return function(val) {
                if (this.dataSource === 'revenue') {
                    return '$' + val.toLocaleString();
                }
                return val;
            }.bind(this);
        },
        
        setChartType(type) {
            this.chartType = type;
            this.initChart();
        },
        
        updateTimeRange() {
            console.log('📅 Updating time range');
            this.loading = true;
            
            // Simulate API call
            setTimeout(() => {
                this.currentData = this.generateMockData();
                this.initChart();
                this.loading = false;
                this.lastUpdated = new Date().toLocaleTimeString();
            }, 1000);
        },
        
        updateDataSource() {
            console.log('📊 Updating data source');
            this.loading = true;
            
            // Simulate API call
            setTimeout(() => {
                this.currentData = this.generateMockData();
                this.initChart();
                this.loading = false;
                this.lastUpdated = new Date().toLocaleTimeString();
            }, 1000);
        },
        
        enableDrillDown() {
            console.log('🔍 Enabling drill-down');
            this.canDrillDown = false;
            
            // Simulate drill-down data
            setTimeout(() => {
                this.currentData = this.generateDrillDownData();
                this.initChart();
                this.canDrillDown = true;
            }, 1000);
        },
        
        handleDrillDown(dataPointIndex) {
            console.log('🔍 Drilling down into data point:', dataPointIndex);
            this.enableDrillDown();
        },
        
        exportChart() {
            console.log('📤 Exporting chart');
            
            if (this.chart) {
                this.chart.dataURI().then((uri) => {
                    const link = document.createElement('a');
                    link.href = uri.imgURI;
                    link.download = `${this.chartId}-chart-${new Date().toISOString().split('T')[0]}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        },
        
        toggleRealTime() {
            this.realTimeEnabled = !this.realTimeEnabled;
            
            if (this.realTimeEnabled) {
                this.startRealTimeUpdates();
            } else {
                this.stopRealTimeUpdates();
            }
        },
        
        startRealTimeUpdates() {
            if (this.realTimeInterval) {
                clearInterval(this.realTimeInterval);
            }
            
            this.realTimeInterval = setInterval(() => {
                this.updateRealTimeData();
            }, 5000); // Update every 5 seconds
        },
        
        stopRealTimeUpdates() {
            if (this.realTimeInterval) {
                clearInterval(this.realTimeInterval);
                this.realTimeInterval = null;
            }
        },
        
        updateRealTimeData() {
            // Add new data point and remove oldest
            const newValue = Math.floor(Math.random() * 1000) + 500;
            this.currentData.push(newValue);
            this.currentData.shift();
            
            this.initChart();
            this.lastUpdated = new Date().toLocaleTimeString();
            this.dataPoints = this.currentData.length;
        },
        
        generateMockData() {
            const data = [];
            for (let i = 0; i < 12; i++) {
                data.push(Math.floor(Math.random() * 1000) + 500);
            }
            return data;
        },
        
        generateDrillDownData() {
            const data = [];
            for (let i = 0; i < 12; i++) {
                data.push(Math.floor(Math.random() * 200) + 100);
            }
            return data;
        },
        
        retry() {
            this.error = null;
            this.loading = true;
            
            setTimeout(() => {
                this.initChart();
                this.loading = false;
            }, 1000);
        }
    };
}
</script>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('styles'); ?>
<style>
.interactive-chart {
    position: relative;
}

.interactive-chart .apexcharts-tooltip {
    background: rgba(0, 0, 0, 0.8);
    color: white;
    border-radius: 8px;
    padding: 8px 12px;
}

.interactive-chart .apexcharts-legend {
    padding-top: 20px;
}

.interactive-chart .apexcharts-legend-series {
    margin-right: 20px;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .interactive-chart .apexcharts-toolbar {
        display: none;
    }
    
    .interactive-chart .apexcharts-legend {
        position: relative !important;
        justify-content: center;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .interactive-chart {
        border: 2px solid #000;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .interactive-chart .apexcharts-chart {
        animation: none !important;
    }
}
</style>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/dashboard/charts/interactive-chart.blade.php ENDPATH**/ ?>