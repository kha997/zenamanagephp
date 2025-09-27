{{--
    Dashboard Layout Template
    Follows ZenaManage Dashboard Design Principles
    
    Usage:
    @extends('layouts.dashboard-layout')
    
    @section('title', 'Dashboard Title')
    
    @section('kpis')
        @include('components.dashboard-kpi-card', [...])
    @endsection
    
    @section('content')
        <!-- Main dashboard content -->
    @endsection
--}}

@extends('layouts.app-layout')

@section('title', $title ?? 'Dashboard - ZenaManage')

@section('content')
<div x-data="dashboardLayout()" x-init="init()" class="min-h-screen bg-gray-50">
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $title ?? 'Dashboard' }}</h1>
                    <p class="text-gray-600 mt-1">{{ $subtitle ?? 'Welcome back! Here\'s what\'s happening with your projects.' }}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Smart Search Trigger -->
                    @include('components.smart-search')
                    
                    <!-- Refresh Button -->
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Refresh dashboard data">
                        <i class="fas fa-sync-alt mr-2" :class="refreshing ? 'animate-spin' : ''" aria-hidden="true"></i>
                        <span x-show="!refreshing">Refresh</span>
                        <span x-show="refreshing">Refreshing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Alert Bar (if alerts exist) -->
        <div x-show="alerts.length > 0" class="mb-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400" aria-hidden="true"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Attention Required</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <template x-for="alert in alerts" :key="alert.id">
                                    <li x-text="alert.message"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- KPI Strip (1-2 rows max) -->
        @hasSection('kpis')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            @yield('kpis')
        </div>
        @endif
        
        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Primary Content (2/3 width) -->
            <div class="lg:col-span-2">
                @yield('primary-content')
            </div>
            
            <!-- Secondary Content (1/3 width) -->
            <div class="lg:col-span-1">
                @yield('secondary-content')
            </div>
        </div>
        
        <!-- Full Width Content -->
        @hasSection('full-width-content')
        <div class="mb-8">
            @yield('full-width-content')
        </div>
        @endif
        
        <!-- Additional Content -->
        @yield('content')
    </div>
    
    <!-- Floating Action Button (Mobile) -->
    <div class="fixed bottom-6 right-6 md:hidden">
        <button @click="openMobileMenu()" 
                class="bg-blue-600 text-white p-4 rounded-full shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Open mobile menu">
            <i class="fas fa-plus text-xl" aria-hidden="true"></i>
        </button>
    </div>
    
    <!-- Mobile Menu Overlay -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
         @click="closeMobileMenu()">
    </div>
    
    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed right-0 top-0 h-full w-80 bg-white shadow-xl z-50 md:hidden">
        
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                <button @click="closeMobileMenu()" 
                        class="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                        aria-label="Close mobile menu">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
            
            <nav class="space-y-2">
                <a href="/app/projects/create" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-plus text-blue-600" aria-hidden="true"></i>
                    <span>Create Project</span>
                </a>
                <a href="/app/tasks/create" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-tasks text-green-600" aria-hidden="true"></i>
                    <span>Create Task</span>
                </a>
                <a href="/app/team/invite" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-user-plus text-purple-600" aria-hidden="true"></i>
                    <span>Invite Team Member</span>
                </a>
                <a href="/app/calendar" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-calendar text-orange-600" aria-hidden="true"></i>
                    <span>View Calendar</span>
                </a>
            </nav>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Dashboard Layout Styles */
    .dashboard-layout {
        min-height: 100vh;
    }
    
    /* KPI Grid Responsive */
    @media (max-width: 768px) {
        .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
            grid-template-columns: 1fr;
        }
    }
    
    @media (min-width: 769px) and (max-width: 1024px) {
        .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    /* Content Grid Responsive */
    @media (max-width: 1024px) {
        .lg\\:col-span-2,
        .lg\\:col-span-1 {
            grid-column: span 1;
        }
    }
    
    /* Focus states for accessibility */
    button:focus,
    a:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }
    
    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .bg-white {
            background-color: #fff;
            border: 1px solid #000;
        }
        
        .text-gray-900 {
            color: #000;
        }
        
        .text-gray-600 {
            color: #333;
        }
    }
    
    /* Reduced motion support */
    @media (prefers-reduced-motion: reduce) {
        .transition,
        .transition-all,
        .transition-colors {
            transition: none;
        }
        
        .animate-spin {
            animation: none;
        }
    }
    
    /* Print styles */
    @media print {
        .fixed,
        .sticky {
            position: static;
        }
        
        .shadow-sm,
        .shadow-lg,
        .shadow-xl {
            box-shadow: none;
        }
        
        .bg-gray-50 {
            background-color: #fff;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function dashboardLayout() {
        return {
            refreshing: false,
            alerts: [],
            mobileMenuOpen: false,
            
            init() {
                console.log('🚀 Dashboard Layout initialized');
                this.loadAlerts();
                this.setupKeyboardShortcuts();
            },
            
            async refreshData() {
                this.refreshing = true;
                console.log('🔄 Refreshing dashboard data...');
                
                try {
                    // Refresh KPIs
                    if (window.refreshAllKPIs) {
                        await window.refreshAllKPIs();
                    }
                    
                    // Refresh other dashboard components
                    if (window.refreshDashboardComponents) {
                        await window.refreshDashboardComponents();
                    }
                    
                    // Show success feedback
                    this.showToast('Dashboard refreshed successfully', 'success');
                    
                } catch (error) {
                    console.error('Error refreshing dashboard:', error);
                    this.showToast('Failed to refresh dashboard', 'error');
                } finally {
                    setTimeout(() => {
                        this.refreshing = false;
                    }, 1000);
                }
            },
            
            async loadAlerts() {
                try {
                    const response = await fetch('/api/v1/app/alerts', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            ...getTenantHeaders()
                        },
                        credentials: 'include'
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.alerts = data.alerts || [];
                    }
                } catch (error) {
                    console.warn('Failed to load alerts:', error);
                }
            },
            
            setupKeyboardShortcuts() {
                document.addEventListener('keydown', (e) => {
                    // Refresh shortcut (Ctrl+R or Cmd+R)
                    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                        e.preventDefault();
                        this.refreshData();
                    }
                    
                    // Mobile menu toggle (M key)
                    if (e.key === 'm' && window.innerWidth < 768) {
                        this.toggleMobileMenu();
                    }
                });
            },
            
            openMobileMenu() {
                this.mobileMenuOpen = true;
                document.body.style.overflow = 'hidden';
            },
            
            closeMobileMenu() {
                this.mobileMenuOpen = false;
                document.body.style.overflow = '';
            },
            
            toggleMobileMenu() {
                if (this.mobileMenuOpen) {
                    this.closeMobileMenu();
                } else {
                    this.openMobileMenu();
                }
            },
            
            showToast(message, type = 'info') {
                // Simple toast implementation
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                    type === 'success' ? 'bg-green-500 text-white' :
                    type === 'error' ? 'bg-red-500 text-white' :
                    'bg-blue-500 text-white'
                }`;
                toast.textContent = message;
                
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        };
    }
    
    // Helper function to get tenant headers
    function getTenantHeaders() {
        const meta = document.querySelector('meta[name="x-tenant-id"]');
        return meta ? { 'X-Tenant-Id': meta.content } : {};
    }
    
    // Global refresh function for KPIs
    window.refreshAllKPIs = async function() {
        const kpiCards = document.querySelectorAll('.kpi-card[data-kpi]');
        const refreshPromises = Array.from(kpiCards).map(card => {
            const kpiKey = card.dataset.kpi;
            return refreshKPI(kpiKey);
        });
        
        await Promise.all(refreshPromises);
    };
    
    // Individual KPI refresh function
    window.refreshKPI = async function(kpiKey) {
        try {
            if (window.showKPILoading) {
                window.showKPILoading(kpiKey);
            }
            
            const response = await fetch(`/api/v1/app/dashboard/kpis/${kpiKey}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    ...getTenantHeaders()
                },
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (window.updateKPIValue) {
                    window.updateKPIValue(kpiKey, data.value, data.trend, data.trend_type);
                }
            } else {
                throw new Error(`Failed to refresh KPI: ${response.status}`);
            }
        } catch (error) {
            console.error(`Error refreshing KPI ${kpiKey}:`, error);
            if (window.showKPIError) {
                window.showKPIError(kpiKey, error.message);
            }
        }
    };
    
    // Global dashboard components refresh
    window.refreshDashboardComponents = async function() {
        // Refresh meetings
        if (window.renderMeetings) {
            try {
                const response = await fetch('/api/v1/app/calendar/events?range=today..+7', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        ...getTenantHeaders()
                    },
                    credentials: 'include'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    window.renderMeetings(data.data || data || []);
                }
            } catch (error) {
                console.warn('Failed to refresh meetings:', error);
            }
        }
        
        // Refresh notifications
        if (window.renderNotifications) {
            try {
                const response = await fetch('/api/v1/app/notifications?unread=true', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        ...getTenantHeaders()
                    },
                    credentials: 'include'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    window.renderNotifications(data.data || data || []);
                }
            } catch (error) {
                console.warn('Failed to refresh notifications:', error);
            }
        }
    };
</script>
@endpush
@endsection
