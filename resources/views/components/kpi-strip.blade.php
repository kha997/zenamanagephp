{{-- KPI Strip Component --}}
{{-- 1-2 rows, 4-8 cards with deep links and real-time updates --}}

<div class="kpi-strip bg-white border-b border-gray-200 sticky top-28 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <!-- KPI Controls -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Key Performance Indicators</h2>
            <div class="flex items-center space-x-2">
                <!-- Row Toggle -->
                <div class="flex items-center space-x-1">
                    <button @click="kpiRows = 1" 
                            class="px-2 py-1 text-xs font-medium rounded transition-colors"
                            :class="kpiRows === 1 ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'">
                        1 Row
                    </button>
                    <button @click="kpiRows = 2" 
                            class="px-2 py-1 text-xs font-medium rounded transition-colors"
                            :class="kpiRows === 2 ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'">
                        2 Rows
                    </button>
                </div>
                
                <!-- Customize Button -->
                <button @click="toggleKPICustomizer" 
                        class="px-3 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-cog mr-1"></i>
                    Customize
                </button>
            </div>
        </div>
        
        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" 
             :class="kpiRows === 2 ? 'lg:grid-cols-4 xl:grid-cols-8' : ''">
            <template x-for="card in visibleKPICards" :key="card.id">
                <div class="kpi-card bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200 hover:shadow-md transition-all duration-200 cursor-pointer"
                     @click="navigateToKPILink(card.link)"
                     @keydown.enter="navigateToKPILink(card.link)"
                     tabindex="0"
                     role="button"
                     :aria-label="`${card.title}: ${card.value}`">
                    <!-- Card Header -->
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-gray-700" x-text="card.title"></h3>
                        <i :class="card.icon" class="text-blue-500 text-lg"></i>
                    </div>
                    
                    <!-- Card Value -->
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold text-gray-900" x-text="card.value"></span>
                        <span class="text-sm font-medium" 
                              :class="card.delta.startsWith('+') ? 'text-green-600' : card.delta.startsWith('-') ? 'text-red-600' : 'text-gray-600'"
                              x-text="card.delta"></span>
                    </div>
                    
                    <!-- Card Footer -->
                    <div class="mt-2 text-xs text-gray-500" x-text="card.period"></div>
                    
                    <!-- Sparkline (if available) -->
                    <div x-show="card.sparkline" class="mt-2 h-8">
                        <svg class="w-full h-full" viewBox="0 0 100 20">
                            <polyline :points="card.sparkline" 
                                     fill="none" 
                                     stroke="#3b82f6" 
                                     stroke-width="2"/>
                        </svg>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- Show More KPIs Button (Mobile) -->
        <div x-show="kpiRows === 2 && isMobile" class="mt-4 text-center">
            <button @click="showMoreKPIs = !showMoreKPIs" 
                    class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                <span x-text="showMoreKPIs ? 'Show Less' : 'Show More KPIs'"></span>
                <i class="fas fa-chevron-down ml-1" :class="showMoreKPIs ? 'rotate-180' : ''"></i>
            </button>
        </div>
    </div>
    
    <!-- KPI Customizer Modal -->
    <div x-show="kpiCustomizerOpen" 
         x-transition
         @click.away="kpiCustomizerOpen = false"
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Customize KPI Cards</h3>
                    <button @click="kpiCustomizerOpen = false" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">Select which KPI cards to display:</p>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="card in allKPICards" :key="card.id">
                            <label class="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" 
                                       :checked="card.visible"
                                       @change="toggleKPICard(card.id)"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-900" x-text="card.title"></div>
                                    <div class="text-xs text-gray-500" x-text="card.description"></div>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>
                
                <div class="flex items-center justify-end space-x-3 mt-6">
                    <button @click="kpiCustomizerOpen = false" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button @click="saveKPIPreferences" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        Save Preferences
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Add to universalFrame Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('universalFrame', () => ({
            // ... existing code ...
            
            // KPI Management
            allKPICards: [
                { id: 1, title: 'Active Projects', value: '12', delta: '+2', period: 'vs last month', link: '/app/projects?status=active', icon: 'fas fa-project-diagram', visible: true, description: 'Currently active projects' },
                { id: 2, title: 'Overdue Tasks', value: '5', delta: '-1', period: 'vs last week', link: '/app/tasks?status=overdue', icon: 'fas fa-tasks', visible: true, description: 'Tasks past due date' },
                { id: 3, title: 'Team Members', value: '8', delta: '+1', period: 'vs last month', link: '/app/team', icon: 'fas fa-users', visible: true, description: 'Active team members' },
                { id: 4, title: 'Documents', value: '24', delta: '+3', period: 'vs last week', link: '/app/documents', icon: 'fas fa-file-alt', visible: true, description: 'Total documents' },
                { id: 5, title: 'Budget Used', value: '78%', delta: '+5%', period: 'vs last month', link: '/app/projects?view=budget', icon: 'fas fa-dollar-sign', visible: false, description: 'Budget utilization' },
                { id: 6, title: 'Completion Rate', value: '92%', delta: '+3%', period: 'vs last month', link: '/app/projects?view=completion', icon: 'fas fa-chart-line', visible: false, description: 'Project completion rate' },
                { id: 7, title: 'Client Satisfaction', value: '4.8', delta: '+0.2', period: 'vs last month', link: '/app/projects?view=satisfaction', icon: 'fas fa-star', visible: false, description: 'Average client rating' },
                { id: 8, title: 'Resource Utilization', value: '85%', delta: '-2%', period: 'vs last week', link: '/app/team?view=utilization', icon: 'fas fa-chart-pie', visible: false, description: 'Team resource usage' }
            ],
            kpiCustomizerOpen: false,
            showMoreKPIs: false,
            isMobile: window.innerWidth < 768,
            
            // Computed Properties
            get visibleKPICards() {
                const visible = this.allKPICards.filter(card => card.visible);
                if (this.kpiRows === 2 && this.isMobile && !this.showMoreKPIs) {
                    return visible.slice(0, 4);
                }
                return visible;
            },
            
            // KPI Actions
            navigateToKPILink(url) {
                window.location.href = url;
            },
            
            toggleKPICustomizer() {
                this.kpiCustomizerOpen = !this.kpiCustomizerOpen;
            },
            
            toggleKPICard(cardId) {
                const card = this.allKPICards.find(c => c.id === cardId);
                if (card) {
                    card.visible = !card.visible;
                }
            },
            
            saveKPIPreferences() {
                const preferences = {
                    kpiRows: this.kpiRows,
                    visibleCards: this.allKPICards.filter(card => card.visible).map(card => card.id)
                };
                localStorage.setItem('kpiPreferences', JSON.stringify(preferences));
                this.kpiCustomizerOpen = false;
            },
            
            // ... rest of existing code ...
        }));
    });
</script>
