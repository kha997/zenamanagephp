{{-- Analysis Drawer Component --}}
{{-- Right drawer with charts for current filter context --}}

<div class="analysis-drawer" x-data="analysisDrawer()">
    <!-- Analysis Toggle Button -->
    <button @click="toggleAnalysis()" 
            class="flex items-center space-x-2 px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
        <i class="fas fa-chart-bar"></i>
        <span>Analyze</span>
        <i class="fas fa-chevron-left text-xs transition-transform" 
           :class="analysisOpen ? 'rotate-180' : ''"></i>
    </button>
    
    <!-- Analysis Drawer -->
    <div x-show="analysisOpen" 
         x-transition
         class="fixed top-0 right-0 h-full w-96 bg-white shadow-xl border-l border-gray-200 z-50 transform transition-transform duration-300"
         :class="analysisOpen ? 'translate-x-0' : 'translate-x-full'">
        
        <!-- Drawer Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Analysis</h3>
            <button @click="analysisOpen = false" 
                    class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Drawer Content -->
        <div class="h-full overflow-y-auto">
            <!-- Loading State -->
            <div x-show="loading" class="p-8 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-sm text-gray-600">Loading analysis...</p>
            </div>
            
            <!-- Analysis Content -->
            <div x-show="!loading && analysisData" class="p-4 space-y-6">
                <!-- Metrics -->
                <div class="grid grid-cols-2 gap-4">
                    <template x-for="metric in analysisData.metrics" :key="metric.title">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-medium text-gray-600" x-text="metric.title"></span>
                                <span class="text-xs font-medium" 
                                      :class="metric.changeType === 'positive' ? 'text-green-600' : metric.changeType === 'negative' ? 'text-red-600' : 'text-gray-600'"
                                      x-text="metric.change"></span>
                            </div>
                            <div class="text-lg font-bold text-gray-900" x-text="metric.value"></div>
                            <div class="text-xs text-gray-500" x-text="metric.description"></div>
                        </div>
                    </template>
                </div>
                
                <!-- Charts -->
                <div class="space-y-6">
                    <template x-for="chart in analysisData.charts" :key="chart.id">
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3" x-text="chart.title"></h4>
                            <div class="h-48" :id="'chart-' + chart.id">
                                <!-- Chart will be rendered here -->
                                <div class="flex items-center justify-center h-full text-gray-500">
                                    <div class="text-center">
                                        <i class="fas fa-chart-bar text-2xl mb-2"></i>
                                        <p class="text-sm">Chart will be rendered here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                
                <!-- Insights -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-blue-900 mb-3">Key Insights</h4>
                    <ul class="space-y-2">
                        <template x-for="insight in analysisData.insights" :key="insight">
                            <li class="flex items-start space-x-2 text-sm text-blue-800">
                                <i class="fas fa-lightbulb text-blue-600 mt-0.5"></i>
                                <span x-text="insight"></span>
                            </li>
                        </template>
                    </ul>
                </div>
                
                <!-- Export Options -->
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Export Analysis</h4>
                    <div class="flex items-center space-x-2">
                        <button @click="exportAnalysis('pdf')" 
                                class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-file-pdf text-red-600"></i>
                            <span>PDF</span>
                        </button>
                        <button @click="exportAnalysis('excel')" 
                                class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-file-excel text-green-600"></i>
                            <span>Excel</span>
                        </button>
                        <button @click="exportAnalysis('csv')" 
                                class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-file-csv text-blue-600"></i>
                            <span>CSV</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Empty State -->
            <div x-show="!loading && !analysisData" class="p-8 text-center">
                <i class="fas fa-chart-bar text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Analysis Available</h3>
                <p class="text-sm text-gray-600 mb-4">Apply filters to see analysis for your data.</p>
                <button @click="refreshAnalysis()" 
                        class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    Refresh Analysis
                </button>
            </div>
        </div>
    </div>
    
    <!-- Backdrop -->
    <div x-show="analysisOpen" 
         @click="analysisOpen = false"
         class="fixed inset-0 bg-black bg-opacity-25 z-40"></div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('analysisDrawer', () => ({
            // State
            analysisOpen: false,
            loading: false,
            analysisData: null,
            currentContext: 'projects',
            currentFilters: {},
            
            // Toggle Analysis
            toggleAnalysis() {
                this.analysisOpen = !this.analysisOpen;
                
                if (this.analysisOpen && !this.analysisData) {
                    this.loadAnalysis();
                }
            },
            
            // Load Analysis
            async loadAnalysis() {
                this.loading = true;
                
                try {
                    const response = await fetch('/api/universal-frame/analysis', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            context: this.currentContext,
                            filters: this.currentFilters
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.analysisData = data.data;
                        this.renderCharts();
                    } else {
                        console.error('Analysis failed:', data.error);
                        this.analysisData = null;
                    }
                } catch (error) {
                    console.error('Analysis error:', error);
                    this.analysisData = null;
                } finally {
                    this.loading = false;
                }
            },
            
            // Refresh Analysis
            async refreshAnalysis() {
                this.analysisData = null;
                await this.loadAnalysis();
            },
            
            // Render Charts
            renderCharts() {
                // This would integrate with Chart.js or similar
                // For now, we'll just show placeholder content
                console.log('Rendering charts:', this.analysisData.charts);
            },
            
            // Export Analysis
            async exportAnalysis(format) {
                try {
                    const response = await fetch('/api/universal-frame/export/analysis', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            format: format,
                            context: this.currentContext,
                            filters: this.currentFilters,
                            analysisData: this.analysisData
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Download file
                        window.open(data.download_url, '_blank');
                    } else {
                        console.error('Export failed:', data.error);
                    }
                } catch (error) {
                    console.error('Export error:', error);
                }
            },
            
            // Set Context
            setContext(context, filters = {}) {
                this.currentContext = context;
                this.currentFilters = filters;
                
                if (this.analysisOpen) {
                    this.refreshAnalysis();
                }
            }
        }));
    });
</script>
