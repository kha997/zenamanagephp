<!-- Chart Widget Component -->
<div x-data="chartWidget({{ json_encode($config) }})" x-init="init()" class="bg-white rounded-lg shadow p-6">
    <!-- Chart Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-medium text-gray-900" x-text="config.title"></h3>
            <p class="text-sm text-gray-500" x-text="config.description"></p>
        </div>
        
        <!-- Chart Controls -->
        <div class="flex items-center space-x-2">
            <!-- Chart Type Selector -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" 
                        class="flex items-center space-x-1 px-3 py-1 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md">
                    <i :class="getChartTypeIcon(config.chartType)" class="text-sm"></i>
                    <span x-text="getChartTypeName(config.chartType)"></span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                
                <div x-show="open" @click.away="open = false" 
                     class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                    <div class="py-1">
                        <template x-for="type in availableChartTypes" :key="type.value">
                            <button @click="changeChartType(type.value); open = false"
                                    class="flex items-center space-x-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i :class="type.icon" class="text-sm"></i>
                                <span x-text="type.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            
            <!-- Export Button -->
            <button @click="exportChart()" 
                    class="p-1 text-gray-400 hover:text-gray-600" 
                    title="Export Chart">
                <i class="fas fa-download"></i>
            </button>
            
            <!-- Fullscreen Button -->
            <button @click="toggleFullscreen()" 
                    class="p-1 text-gray-400 hover:text-gray-600" 
                    title="Fullscreen">
                <i class="fas fa-expand"></i>
            </button>
        </div>
    </div>

    <!-- Chart Container -->
    <div class="relative">
        <div :class="fullscreen ? 'fixed inset-0 z-50 bg-white p-6' : ''">
            <div class="chart-container" :style="fullscreen ? 'height: calc(100vh - 120px);' : 'height: 300px;'">
                <canvas :id="'chart-' + config.id"></canvas>
            </div>
            
            <!-- Loading State -->
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75">
                <div class="flex items-center space-x-2">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                    <span class="text-sm text-gray-600">Loading chart...</span>
                </div>
            </div>
            
            <!-- Error State -->
            <div x-show="error" class="absolute inset-0 flex items-center justify-center bg-red-50">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                    <p class="text-sm text-red-600" x-text="error"></p>
                    <button @click="loadChart()" 
                            class="mt-2 px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">
                        Retry
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Legend (if applicable) -->
    <div x-show="config.showLegend && !loading && !error" class="mt-4">
        <div class="flex flex-wrap items-center justify-center space-x-4">
            <template x-for="(dataset, index) in chartData.datasets" :key="index">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full" :style="'background-color: ' + dataset.backgroundColor"></div>
                    <span class="text-sm text-gray-600" x-text="dataset.label"></span>
                </div>
            </template>
        </div>
    </div>

    <!-- Chart Statistics -->
    <div x-show="config.showStats && !loading && !error" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <template x-for="stat in chartStats" :key="stat.label">
                <div class="text-center">
                    <div class="text-lg font-semibold text-gray-900" x-text="stat.value"></div>
                    <div class="text-xs text-gray-500" x-text="stat.label"></div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('chartWidget', (config) => ({
        config: config,
        chart: null,
        loading: true,
        error: null,
        fullscreen: false,
        chartData: {
            labels: [],
            datasets: []
        },
        chartStats: [],
        availableChartTypes: [
            { value: 'line', label: 'Line Chart', icon: 'fas fa-chart-line' },
            { value: 'bar', label: 'Bar Chart', icon: 'fas fa-chart-bar' },
            { value: 'doughnut', label: 'Doughnut Chart', icon: 'fas fa-chart-pie' },
            { value: 'pie', label: 'Pie Chart', icon: 'fas fa-chart-pie' },
            { value: 'radar', label: 'Radar Chart', icon: 'fas fa-chart-area' },
            { value: 'scatter', label: 'Scatter Plot', icon: 'fas fa-chart-scatter' }
        ],

        async init() {
            await this.loadChart();
        },

        async loadChart() {
            this.loading = true;
            this.error = null;

            try {
                // Load chart data
                const response = await fetch(`/api/v1/app/charts/${this.config.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        type: this.config.chartType,
                        filters: this.config.filters || {},
                        options: this.config.options || {}
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.chartData = data.data.chartData;
                    this.chartStats = data.data.stats || [];
                    this.createChart();
                } else {
                    this.error = data.error?.message || 'Failed to load chart data';
                }
            } catch (error) {
                console.error('Chart loading error:', error);
                this.error = 'Failed to load chart data';
            } finally {
                this.loading = false;
            }
        },

        createChart() {
            const ctx = document.getElementById(`chart-${this.config.id}`);
            if (!ctx) return;

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            // Create new chart
            this.chart = new Chart(ctx, {
                type: this.config.chartType,
                data: this.chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: this.config.showLegend,
                            position: 'bottom'
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: this.getScales(),
                    elements: this.getElementOptions()
                }
            });
        },

        getScales() {
            if (this.config.chartType === 'doughnut' || this.config.chartType === 'pie') {
                return {};
            }

            return {
                x: {
                    display: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                y: {
                    display: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    beginAtZero: true
                }
            };
        },

        getElementOptions() {
            return {
                point: {
                    radius: 4,
                    hoverRadius: 6
                },
                line: {
                    tension: 0.4
                },
                bar: {
                    borderRadius: 4
                }
            };
        },

        changeChartType(newType) {
            this.config.chartType = newType;
            this.loadChart();
        },

        getChartTypeIcon(type) {
            const typeMap = {
                'line': 'fas fa-chart-line',
                'bar': 'fas fa-chart-bar',
                'doughnut': 'fas fa-chart-pie',
                'pie': 'fas fa-chart-pie',
                'radar': 'fas fa-chart-area',
                'scatter': 'fas fa-chart-scatter'
            };
            return typeMap[type] || 'fas fa-chart-bar';
        },

        getChartTypeName(type) {
            const nameMap = {
                'line': 'Line',
                'bar': 'Bar',
                'doughnut': 'Doughnut',
                'pie': 'Pie',
                'radar': 'Radar',
                'scatter': 'Scatter'
            };
            return nameMap[type] || 'Chart';
        },

        toggleFullscreen() {
            this.fullscreen = !this.fullscreen;
            if (this.fullscreen) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
            
            // Resize chart after fullscreen toggle
            this.$nextTick(() => {
                if (this.chart) {
                    this.chart.resize();
                }
            });
        },

        async exportChart() {
            if (!this.chart) return;

            try {
                const canvas = this.chart.canvas;
                const dataURL = canvas.toDataURL('image/png');
                
                // Create download link
                const link = document.createElement('a');
                link.download = `${this.config.title.replace(/\s+/g, '_')}_chart.png`;
                link.href = dataURL;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Chart export error:', error);
            }
        }
    }));
});
</script>
