@extends('layouts.app')

@section('title', 'Template Analytics')

@section('content')
<div class="min-h-screen bg-gray-50" x-data="templateAnalytics()">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="flex items-center space-x-4">
                                <li>
                                    <a href="{{ route('templates') }}" class="text-gray-400 hover:text-gray-500">
                                        <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                        </svg>
                                        <span class="sr-only">Templates</span>
                                    </a>
                                </li>
                                <li>
                                    <div class="flex items-center">
                                        <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="ml-4 text-sm font-medium text-gray-500">Analytics</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="mt-2 text-2xl font-bold text-gray-900">Template Analytics</h1>
                        <p class="mt-1 text-sm text-gray-600">Insights into template usage and performance</p>
                    </div>
                    <div class="flex space-x-3">
                        <select x-model="selectedPeriod" @change="loadAnalytics()" 
                                class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                        </select>
                        <button @click="exportAnalytics()" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Templates</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="analytics.total_templates || 0"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Usage</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="analytics.total_usage || 0"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Public Templates</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="analytics.public_templates || 0"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Avg Usage/Template</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="averageUsage"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Usage Chart -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Template Usage Over Time</h3>
                </div>
                <div class="p-6">
                    <div class="h-64 flex items-center justify-center">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Chart will be rendered here</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Distribution -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Templates by Category</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <template x-for="(count, category) in analytics.categories" :key="category">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-3" 
                                         :class="getCategoryColor(category)"></div>
                                    <span class="text-sm font-medium text-gray-900" x-text="category"></span>
                                </div>
                                <span class="text-sm text-gray-500" x-text="count"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Templates -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Most Popular Templates</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <template x-for="template in analytics.popular_templates" :key="template.id">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900" x-text="template.name"></h4>
                                <p class="text-sm text-gray-500" x-text="template.description"></p>
                                <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" 
                                          x-text="template.category"></span>
                                    <span x-text="`${template.usage_count} uses`"></span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button @click="viewTemplate(template.id)" 
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    View
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Template Activity</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <template x-for="template in analytics.recent_templates" :key="template.id">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900" x-text="template.name"></p>
                                    <p class="text-sm text-gray-500" x-text="`Created ${formatDate(template.created_at)}`"></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500" x-text="`${template.usage_count} uses`"></span>
                                <button @click="viewTemplate(template.id)" 
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    View
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function templateAnalytics() {
    return {
        analytics: {},
        selectedPeriod: '30',

        init() {
            this.loadAnalytics();
        },

        async loadAnalytics() {
            try {
                const response = await fetch(`/api/v1/app/templates/analytics?period=${this.selectedPeriod}`, {
                    headers: {
                        'Authorization': 'Bearer ' + this.getAuthToken()
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.analytics = data.data;
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
            }
        },

        get averageUsage() {
            if (!this.analytics.total_templates || !this.analytics.total_usage) return '0';
            return Math.round(this.analytics.total_usage / this.analytics.total_templates);
        },

        getCategoryColor(category) {
            const colors = {
                'project': 'bg-blue-500',
                'task': 'bg-green-500',
                'workflow': 'bg-purple-500',
                'document': 'bg-yellow-500',
                'report': 'bg-red-500'
            };
            return colors[category] || 'bg-gray-500';
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },

        viewTemplate(templateId) {
            window.location.href = `/app/templates/${templateId}`;
        },

        async exportAnalytics() {
            try {
                const response = await fetch(`/api/v1/app/templates/analytics/export?period=${this.selectedPeriod}`, {
                    headers: {
                        'Authorization': 'Bearer ' + this.getAuthToken()
                    }
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `template-analytics-${this.selectedPeriod}days.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            } catch (error) {
                console.error('Error exporting analytics:', error);
            }
        },

        getAuthToken() {
            // Mock implementation - replace with actual token retrieval
            return 'mock-token';
        }
    }
}
</script>
@endsection
