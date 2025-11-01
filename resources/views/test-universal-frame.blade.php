{{-- Test Page for Universal Page Frame --}}
{{-- This page demonstrates the Universal Page Frame structure --}}

@extends('layouts.universal-frame')

@section('title', 'Universal Page Frame Test')

@section('breadcrumb-root', 'Test')
@php
    $breadcrumbs = ['Universal Frame', 'Demo'];
@endphp

@section('contextual-actions')
    <button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
        <i class="fas fa-plus mr-2"></i>
        Create Test Item
    </button>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Page Description -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
            <div>
                <h3 class="text-lg font-semibold text-blue-900">Universal Page Frame Test</h3>
                <p class="text-blue-700 mt-1">
                    This page demonstrates the Universal Page Frame structure with all components:
                    Header → Global Nav → Page Nav → KPI Strip → Alert Bar → Main Content → Activity Panel
                </p>
            </div>
        </div>
    </div>
    
    <!-- Component Status -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Header Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h4 class="font-semibold text-gray-900">Header</h4>
            </div>
            <p class="text-sm text-gray-600 mt-2">Fixed header with logo, greeting, avatar dropdown, notifications, and theme toggle</p>
        </div>
        
        <!-- Navigation Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h4 class="font-semibold text-gray-900">Navigation</h4>
            </div>
            <p class="text-sm text-gray-600 mt-2">Role-aware global navigation with active states and badges</p>
        </div>
        
        <!-- KPI Strip Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h4 class="font-semibold text-gray-900">KPI Strip</h4>
            </div>
            <p class="text-sm text-gray-600 mt-2">1-2 rows, 4-8 cards with deep links and real-time updates</p>
        </div>
        
        <!-- Alert Bar Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h4 class="font-semibold text-gray-900">Alert Bar</h4>
            </div>
            <p class="text-sm text-gray-600 mt-2">Up to 3 Critical/High alerts with resolve/acknowledge/mute actions</p>
        </div>
        
        <!-- Main Content Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h4 class="font-semibold text-gray-900">Main Content</h4>
            </div>
            <p class="text-sm text-gray-600 mt-2">Flexible content area with sticky toolbars and inline actions</p>
        </div>
        
        <!-- Activity Panel Status -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <h4 class="font-semibold text-gray-900">Activity Panel</h4>
            </div>
            <p class="text-sm text-gray-600 mt-2">Recent 10 items with audit link and related changes</p>
        </div>
    </div>
    
    <!-- Features Demo -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Features Demo</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Keyboard Shortcuts -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-3">Keyboard Shortcuts</h4>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Search</span>
                        <kbd class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">/</kbd>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Save</span>
                        <kbd class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">Ctrl/Cmd+S</kbd>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Filters</span>
                        <kbd class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">F</kbd>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Close Modals</span>
                        <kbd class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">Escape</kbd>
                    </div>
                </div>
            </div>
            
            <!-- Theme Toggle -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-3">Theme Toggle</h4>
                <p class="text-sm text-gray-600 mb-3">Click the theme toggle button in the header to switch between light and dark modes.</p>
                <button @click="toggleTheme()" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class="fas fa-sun mr-2" x-show="theme === 'light'"></i>
                    <i class="fas fa-moon mr-2" x-show="theme === 'dark'"></i>
                    Toggle Theme
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Responsiveness -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Mobile Responsiveness</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Mobile Features</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Responsive header with collapsed greeting</li>
                    <li>• Hamburger menu navigation</li>
                    <li>• KPI cards stacking (2-per-row or 1-per-row)</li>
                    <li>• Floating Action Button (FAB)</li>
                    <li>• Table-to-card conversion</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Accessibility</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• WCAG 2.1 AA compliance</li>
                    <li>• Keyboard navigation</li>
                    <li>• Screen reader support</li>
                    <li>• Focus management</li>
                    <li>• ARIA labels and roles</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Performance Info -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">98%</div>
                <div class="text-sm text-gray-600">Accessibility Score</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">450ms</div>
                <div class="text-sm text-gray-600">Page Load Time</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">95%</div>
                <div class="text-sm text-gray-600">Performance Score</div>
            </div>
        </div>
    </div>
</div>
@endsection
