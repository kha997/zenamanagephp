@extends('layouts.app')

@section('title', __('settings.title'))

@section('kpi-strip')
{{-- <x-kpi.strip :kpis="$kpis" /> --}}
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('settings.title') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('settings.subtitle') }}</p>
            </div>
            <div class="flex space-x-3">
                <a href="/admin" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>{{ __('settings.back_to_admin') }}
                </a>
            </div>
        </div>
    </div>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div x-data="settingsDashboard()" x-init="init()">
            <!-- Settings Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">System Status</p>
                            <p class="text-3xl font-bold text-white">Online</p>
                            <p class="text-white/80 text-sm">
                                All services running
                            </p>
                        </div>
                        <i class="fas fa-server text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Uptime</p>
                            <p class="text-3xl font-bold text-white">99.9%</p>
                            <p class="text-white/80 text-sm">
                                Last 30 days
                            </p>
                        </div>
                        <i class="fas fa-clock text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Active Users</p>
                            <p class="text-3xl font-bold text-white">156</p>
                            <p class="text-white/80 text-sm">
                                Currently online
                            </p>
                        </div>
                        <i class="fas fa-users text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Storage Used</p>
                            <p class="text-3xl font-bold text-white">2.3TB</p>
                            <p class="text-white/80 text-sm">
                                of 5TB total
                            </p>
                        </div>
                        <i class="fas fa-hdd text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Settings Sections -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- General Settings -->
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">General Settings</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Site Name</span>
                            <span class="text-sm font-medium text-gray-900">ZenaManage</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Admin Email</span>
                            <span class="text-sm font-medium text-gray-900">admin@zenamanage.com</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Timezone</span>
                            <span class="text-sm font-medium text-gray-900">UTC+7</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Language</span>
                            <span class="text-sm font-medium text-gray-900">English</span>
                        </div>
                        <button class="zena-btn zena-btn-outline zena-btn-sm w-full">
                            <i class="fas fa-edit mr-2"></i>Edit General Settings
                        </button>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Settings</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Two-Factor Auth</span>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Enabled</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Session Timeout</span>
                            <span class="text-sm font-medium text-gray-900">30 minutes</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">Password Policy</span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Strong</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">IP Whitelist</span>
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">Disabled</span>
                        </div>
                        <button class="zena-btn zena-btn-outline zena-btn-sm w-full">
                            <i class="fas fa-shield-alt mr-2"></i>Edit Security Settings
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Configuration -->
            <div class="dashboard-card p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">System Configuration</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">Database</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Status</span>
                                <span class="text-green-600 font-medium">Connected</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Version</span>
                                <span class="text-gray-900">MySQL 8.0</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Size</span>
                                <span class="text-gray-900">2.1GB</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">Cache</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Status</span>
                                <span class="text-green-600 font-medium">Active</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Driver</span>
                                <span class="text-gray-900">Redis</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Hit Rate</span>
                                <span class="text-gray-900">94.2%</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-3">Queue</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Status</span>
                                <span class="text-green-600 font-medium">Running</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Driver</span>
                                <span class="text-gray-900">Redis</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Pending Jobs</span>
                                <span class="text-gray-900">12</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function settingsDashboard() {
        return {
            init() {
                // Initialize dashboard
            }
        }
    }
</script>
@endpush
@endsection
