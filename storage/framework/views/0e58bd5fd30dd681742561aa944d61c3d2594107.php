<?php $__env->startSection('title', 'Template Analytics'); ?>
<?php $__env->startSection('page-title', 'Template Analytics'); ?>
<?php $__env->startSection('page-description', 'Analyze template performance and usage statistics'); ?>
<?php $__env->startSection('user-initials', 'TA'); ?>
<?php $__env->startSection('user-name', 'Template Analyst'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="templateAnalytics()" class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Template Analytics</h1>
            <p class="text-gray-600 mt-1">Analyze template performance and usage patterns</p>
        </div>
        <div class="flex space-x-3">
            <select 
                x-model="selectedPeriod"
                @change="updateAnalytics()"
                class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
                <option value="7d">Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="90d">Last 90 days</option>
                <option value="1y">Last year</option>
            </select>
            <button 
                @click="exportReport()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
                <i class="fas fa-download mr-2"></i>Export Report
            </button>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-layer-group text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Templates</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="analytics.totalTemplates"></p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-play text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Projects Created</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="analytics.projectsCreated"></p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="p-3 bg-orange-100 rounded-lg">
                    <i class="fas fa-clock text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Avg. Time Saved</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="analytics.avgTimeSaved + 'h'"></p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-card p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-star text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Success Rate</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="analytics.successRate + '%'"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Template Usage Chart -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Template Usage</h3>
            <div class="h-64">
                <canvas id="usageChart"></canvas>
            </div>
        </div>
        
        <!-- Category Distribution -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📈 Category Distribution</h3>
            <div class="h-64">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Template Performance Table -->
    <div class="dashboard-card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-gray-900">🎯 Template Performance</h3>
            <div class="flex space-x-2">
                <input 
                    type="text" 
                    x-model="searchQuery"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Search templates..."
                >
                <select 
                    x-model="sortBy"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="usage">Usage Count</option>
                    <option value="success_rate">Success Rate</option>
                    <option value="time_saved">Time Saved</option>
                    <option value="rating">Rating</option>
                </select>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Time Saved</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="template in filteredTemplates" :key="template.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div 
                                            class="h-10 w-10 rounded-lg flex items-center justify-center text-white font-bold"
                                            :class="getCategoryColor(template.category)"
                                        >
                                            <span x-text="template.name.charAt(0)"></span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900" x-text="template.name"></div>
                                        <div class="text-sm text-gray-500" x-text="template.phases_count + ' phases'"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span 
                                    class="px-2 py-1 text-xs font-medium rounded-full capitalize"
                                    :class="getCategoryBadgeClass(template.category)"
                                    x-text="template.category"
                                ></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="template.usage_count"></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                        <div 
                                            class="bg-green-500 h-2 rounded-full"
                                            :style="`width: ${template.success_rate}%`"
                                        ></div>
                                    </div>
                                    <span class="text-sm text-gray-900" x-text="template.success_rate + '%'"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="template.avg_time_saved + 'h'"></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <template x-for="i in 5" :key="i">
                                        <i 
                                            class="fas fa-star text-sm"
                                            :class="i <= template.rating ? 'text-yellow-400' : 'text-gray-300'"
                                        ></i>
                                    </template>
                                    <span class="ml-2 text-sm text-gray-500" x-text="template.rating"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button 
                                        @click="viewTemplate(template)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button 
                                        @click="editTemplate(template)"
                                        class="text-green-600 hover:text-green-900"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button 
                                        @click="duplicateTemplate(template)"
                                        class="text-purple-600 hover:text-purple-900"
                                    >
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Template Insights -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Most Popular Templates -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🏆 Most Popular</h3>
            <div class="space-y-3">
                <template x-for="(template, index) in analytics.mostPopular" :key="template.id">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-sm">
                                <span x-text="index + 1"></span>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900" x-text="template.name"></div>
                                <div class="text-xs text-gray-500" x-text="template.usage_count + ' uses'"></div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500" x-text="template.success_rate + '%'"></div>
                    </div>
                </template>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 Recent Activity</h3>
            <div class="space-y-3">
                <template x-for="activity in analytics.recentActivity" :key="activity.id">
                    <div class="flex items-start space-x-3">
                        <div 
                            class="w-2 h-2 rounded-full mt-2"
                            :class="getActivityColor(activity.type)"
                        ></div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-900" x-text="activity.description"></div>
                            <div class="text-xs text-gray-500" x-text="activity.time"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <!-- Performance Insights -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">💡 Insights</h3>
            <div class="space-y-3">
                <template x-for="insight in analytics.insights" :key="insight.id">
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-lightbulb text-blue-600 mt-1 mr-2"></i>
                            <div>
                                <div class="text-sm font-medium text-blue-900" x-text="insight.title"></div>
                                <div class="text-xs text-blue-700 mt-1" x-text="insight.description"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function templateAnalytics() {
    return {
        selectedPeriod: '30d',
        searchQuery: '',
        sortBy: 'usage',
        
        analytics: {
            totalTemplates: 24,
            projectsCreated: 156,
            avgTimeSaved: 12.5,
            successRate: 87,
            mostPopular: [
                { id: 1, name: 'Residential Design Template', usage_count: 45, success_rate: 92 },
                { id: 2, name: 'Commercial Design Template', usage_count: 38, success_rate: 89 },
                { id: 3, name: 'Industrial Design Template', usage_count: 28, success_rate: 85 }
            ],
            recentActivity: [
                { id: 1, type: 'created', description: 'New project created using Residential Template', time: '2 hours ago' },
                { id: 2, type: 'updated', description: 'Commercial Template updated with new phases', time: '4 hours ago' },
                { id: 3, type: 'applied', description: 'Industrial Template applied to Project Alpha', time: '6 hours ago' }
            ],
            insights: [
                { id: 1, title: 'High Success Rate', description: 'Residential templates have 92% success rate' },
                { id: 2, title: 'Time Savings', description: 'Templates save average 12.5 hours per project' },
                { id: 3, title: 'Popular Categories', description: 'Residential and Commercial are most used' }
            ]
        },
        
        templates: [
            {
                id: 1,
                name: 'Residential Design Template',
                category: 'residential',
                usage_count: 45,
                success_rate: 92,
                avg_time_saved: 15,
                rating: 4.8,
                phases_count: 5
            },
            {
                id: 2,
                name: 'Commercial Design Template',
                category: 'commercial',
                usage_count: 38,
                success_rate: 89,
                avg_time_saved: 18,
                rating: 4.6,
                phases_count: 4
            },
            {
                id: 3,
                name: 'Industrial Design Template',
                category: 'industrial',
                usage_count: 28,
                success_rate: 85,
                avg_time_saved: 22,
                rating: 4.4,
                phases_count: 6
            },
            {
                id: 4,
                name: 'Mixed-use Design Template',
                category: 'mixed-use',
                usage_count: 15,
                success_rate: 78,
                avg_time_saved: 20,
                rating: 4.2,
                phases_count: 7
            }
        ],
        
        get filteredTemplates() {
            let filtered = this.templates;
            
            if (this.searchQuery) {
                filtered = filtered.filter(template => 
                    template.name.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            
            return filtered.sort((a, b) => {
                switch (this.sortBy) {
                    case 'usage':
                        return b.usage_count - a.usage_count;
                    case 'success_rate':
                        return b.success_rate - a.success_rate;
                    case 'time_saved':
                        return b.avg_time_saved - a.avg_time_saved;
                    case 'rating':
                        return b.rating - a.rating;
                    default:
                        return 0;
                }
            });
        },
        
        getCategoryColor(category) {
            const colorMap = {
                residential: 'bg-blue-500',
                commercial: 'bg-green-500',
                industrial: 'bg-orange-500',
                'mixed-use': 'bg-purple-500',
                custom: 'bg-gray-500'
            };
            return colorMap[category] || 'bg-gray-500';
        },
        
        getCategoryBadgeClass(category) {
            const badgeMap = {
                residential: 'bg-blue-100 text-blue-800',
                commercial: 'bg-green-100 text-green-800',
                industrial: 'bg-orange-100 text-orange-800',
                'mixed-use': 'bg-purple-100 text-purple-800',
                custom: 'bg-gray-100 text-gray-800'
            };
            return badgeMap[category] || 'bg-gray-100 text-gray-800';
        },
        
        getActivityColor(type) {
            const colorMap = {
                created: 'bg-green-500',
                updated: 'bg-blue-500',
                applied: 'bg-purple-500',
                deleted: 'bg-red-500'
            };
            return colorMap[type] || 'bg-gray-500';
        },
        
        updateAnalytics() {
            // Update analytics based on selected period
            console.log('Updating analytics for period:', this.selectedPeriod);
        },
        
        exportReport() {
            alert('Exporting analytics report...');
        },
        
        viewTemplate(template) {
            alert(`Viewing template: ${template.name}`);
        },
        
        editTemplate(template) {
            alert(`Editing template: ${template.name}`);
        },
        
        duplicateTemplate(template) {
            alert(`Duplicating template: ${template.name}`);
        },
        
        init() {
            // Initialize charts
            this.$nextTick(() => {
                this.initUsageChart();
                this.initCategoryChart();
            });
        },
        
        initUsageChart() {
            const ctx = document.getElementById('usageChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Template Usage',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },
        
        initCategoryChart() {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Residential', 'Commercial', 'Industrial', 'Mixed-use'],
                    datasets: [{
                        data: [45, 38, 28, 15],
                        backgroundColor: [
                            'rgb(59, 130, 246)',
                            'rgb(34, 197, 94)',
                            'rgb(249, 115, 22)',
                            'rgb(168, 85, 247)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/templates/analytics.blade.php ENDPATH**/ ?>