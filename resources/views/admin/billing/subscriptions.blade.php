{{-- Admin Billing Subscriptions --}}
@extends('layouts.admin')

@section('title', 'Billing Subscriptions')

@section('breadcrumb')
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Billing</span>
</li>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Subscriptions</span>
</li>
@endsection

@section('content')
<div class="space-y-6" x-data="billingSubscriptions()" x-init="init()">
    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="/admin/billing" class="text-blue-600 hover:text-blue-800">Billing</a>
                <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                <span class="text-gray-900">Subscriptions</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">Billing Subscriptions</h1>
            <p class="text-gray-600">Manage and monitor subscription plans</p>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="exportData()" 
                    :disabled="isLoading"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
            <button @click="refreshData()" 
                    :disabled="isLoading"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                <i :class="isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt'" class="mr-2"></i>
                Refresh
            </button>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- Status Filter --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select x-model="filters.status" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="canceled">Canceled</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            {{-- Plan Filter --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                <select x-model="filters.plan" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Plans</option>
                    <option value="basic">Basic</option>
                    <option value="professional">Professional</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>

            {{-- Tenant Search --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                <input type="text" x-model="filters.tenant_q" @input.debounce.300ms="applyFilters()" 
                       placeholder="Search tenants..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Range Filter --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Time Range</label>
                <select x-model="filters.range" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="last_30d">Last 30 Days</option>
                    <option value="last_90d">Last 90 Days</option>
                    <option value="this_month">This Month</option>
                    <option value="YTD">Year to Date</option>
                    <option value="last_12m">Last 12 Months</option>
                </select>
            </div>

            {{-- Per Page --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                <select x-model="filters.per_page" @change="applyFilters()" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Loading State --}}
    <div x-show="isLoading" class="flex items-center justify-center py-12">
        <div class="text-center">
            <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
            <p class="text-gray-600">Loading subscriptions...</p>
        </div>
    </div>

    {{-- Error State --}}
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
            <div>
                <h3 class="text-sm font-medium text-red-800">Error loading subscriptions</h3>
                <p class="text-sm text-red-700 mt-1" x-text="error"></p>
            </div>
            <button @click="refreshData()" class="ml-auto px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                Retry
            </button>
        </div>
    </div>

    {{-- Main Content --}}
    <div x-show="!isLoading && !error" class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="bg-blue-100 rounded-full p-2 mr-3">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total Subscriptions</p>
                        <p class="text-xl font-bold text-gray-900" x-text="meta?.total || 0">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="bg-green-100 rounded-full p-2 mr-3">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Active</p>
                        <p class="text-xl font-bold text-gray-900" x-text="getActiveCount()">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="bg-red-100 rounded-full p-2 mr-3">
                        <i class="fas fa-times-circle text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Canceled</p>
                        <p class="text-xl font-bold text-gray-900" x-text="getCanceledCount()">0</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Subscriptions Table --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Subscription
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tenant
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Plan
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Started
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Renews
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="subscription in subscriptions" :key="subscription.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900" x-text="subscription.id"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="subscription.tenant"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                          :class="getPlanBadgeClass(subscription.plan)"
                                          x-text="subscription.plan">
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="getStatusBadgeClass(subscription.status)"
                                          x-text="subscription.status">
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="formatCurrency(subscription.amount)"></div>
                                    <div class="text-xs text-gray-500" x-text="subscription.currency"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="formatDate(subscription.started_at)"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="formatDate(subscription.renew_at)"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button @click="viewSubscription(subscription)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                        View
                                    </button>
                                    <button @click="editSubscription(subscription)" 
                                            class="text-indigo-600 hover:text-indigo-900">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Empty State --}}
            <div x-show="subscriptions.length === 0" class="text-center py-12">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No subscriptions found</h3>
                <p class="text-gray-500">Try adjusting your filters to see more results.</p>
            </div>
        </div>

        {{-- Pagination --}}
        <div x-show="meta && meta.total > 0" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <span x-text="meta.from"></span> to <span x-text="meta.to"></span> of <span x-text="meta.total"></span> results
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="goToPage(meta.current_page - 1)" 
                            :disabled="!meta.links.prev"
                            class="px-3 py-1 border border-gray-300 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">
                        Previous
                    </button>
                    <span class="px-3 py-1 bg-blue-600 text-white rounded text-sm" x-text="meta.current_page"></span>
                    <button @click="goToPage(meta.current_page + 1)" 
                            :disabled="!meta.links.next"
                            class="px-3 py-1 border border-gray-300 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function billingSubscriptions() {
    return {
        isLoading: true,
        error: null,
        subscriptions: [],
        meta: null,
        filters: {
            status: '',
            plan: '',
            tenant_q: '',
            range: 'last_30d',
            per_page: 20
        },

        async init() {
            this.loadFiltersFromURL();
            await this.loadData();
        },

        async loadData() {
            try {
                this.isLoading = true;
                this.error = null;

                const response = await fetch(`/api/admin/billing/subscriptions?${this.buildQueryString()}`);
                if (!response.ok) throw new Error('Failed to load subscriptions');
                
                const data = await response.json();
                this.subscriptions = data.data.data || [];
                this.meta = data.data.meta || null;

            } catch (error) {
                console.error('Error loading subscriptions:', error);
                this.error = error.message;
            } finally {
                this.isLoading = false;
            }
        },

        async refreshData() {
            await this.loadData();
        },

        applyFilters() {
            this.syncURL();
            this.loadData();
        },

        buildQueryString() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.append(key, value);
            });
            return params.toString();
        },

        loadFiltersFromURL() {
            const url = new URL(window.location);
            Object.keys(this.filters).forEach(key => {
                if (url.searchParams.has(key)) {
                    this.filters[key] = url.searchParams.get(key);
                }
            });
        },

        syncURL() {
            const url = new URL(window.location);
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });
            window.history.replaceState({}, '', url);
        },

        async goToPage(page) {
            if (page < 1 || (this.meta && page > this.meta.last_page)) return;
            
            // Add page to filters and reload
            const params = new URLSearchParams(this.buildQueryString());
            params.set('page', page);
            
            try {
                this.isLoading = true;
                const response = await fetch(`/api/admin/billing/subscriptions?${params.toString()}`);
                if (!response.ok) throw new Error('Failed to load subscriptions');
                
                const data = await response.json();
                this.subscriptions = data.data.data || [];
                this.meta = data.data.meta || null;

                // Update URL
                url.searchParams.set('page', page);
                window.history.replaceState({}, '', url);

            } catch (error) {
                console.error('Error loading subscriptions:', error);
                this.error = error.message;
            } finally {
                this.isLoading = false;
            }
        },

        async exportData() {
            try {
                const params = new URLSearchParams(this.buildQueryString());
                const response = await fetch(`/api/admin/billing/subscriptions/export?${params.toString()}`);
                
                if (!response.ok) throw new Error('Failed to export subscriptions');
                
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `subscriptions_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

            } catch (error) {
                console.error('Error exporting subscriptions:', error);
                alert('Failed to export subscriptions. Please try again.');
            }
        },

        getActiveCount() {
            return this.subscriptions.filter(sub => sub.status === 'active').length;
        },

        getCanceledCount() {
            return this.subscriptions.filter(sub => sub.status === 'canceled').length;
        },

        getPlanBadgeClass(plan) {
            return {
                'bg-blue-100 text-blue-800': plan === 'basic',
                'bg-green-100 text-green-800': plan === 'professional',
                'bg-purple-100 text-purple-800': plan === 'enterprise',
                'bg-gray-100 text-gray-800': !['basic', 'professional', 'enterprise'].includes(plan)
            };
        },

        getStatusBadgeClass(status) {
            return {
                'bg-green-100 text-green-800': status === 'active',
                'bg-red-100 text-red-800': status === 'canceled',
                'bg-yellow-100 text-yellow-800': status === 'suspended',
                'bg-gray-100 text-gray-800': !['active', 'canceled', 'suspended'].includes(status)
            };
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString();
        },

        viewSubscription(subscription) {
            // Implement view subscription modal or page
            console.log('View subscription:', subscription);
        },

        editSubscription(subscription) {
            // Implement edit subscription modal or page
            console.log('Edit subscription:', subscription);
        }
    }
}
</script>
@endsection
