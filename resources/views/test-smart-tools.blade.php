{{-- Smart Tools Test Page --}}
{{-- This page demonstrates the Smart Tools functionality --}}

@extends('layouts.universal-frame')

@section('title', 'Smart Tools Test')

@section('breadcrumb-root', 'Test')
@php
    $breadcrumbs = ['Smart Tools', 'Demo'];
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
                <h3 class="text-lg font-semibold text-blue-900">Smart Tools Test</h3>
                <p class="text-blue-700 mt-1">
                    This page demonstrates the Smart Tools functionality including:
                    Intelligent Search, Smart Filters, Analysis & Export capabilities
                </p>
            </div>
        </div>
    </div>
    
    <!-- Smart Search Demo -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Intelligent Search</h3>
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-3">
                Try searching for projects, tasks, documents, or users. The search includes:
            </p>
            <ul class="text-sm text-gray-600 space-y-1 ml-4">
                <li>• Fuzzy matching on names, codes, and descriptions</li>
                <li>• Recent searches history</li>
                <li>• Search suggestions and autocomplete</li>
                <li>• Role-aware results (admin vs tenant)</li>
                <li>• Keyboard shortcuts (press / to focus)</li>
            </ul>
        </div>
        
        <!-- Smart Search Component -->
        @include('components.smart-search')
        
        <!-- Search Tips -->
        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Search Tips:</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-600">
                <div>• Try: "website" or "WR-2024"</div>
                <div>• Try: "overdue" or "high priority"</div>
                <div>• Try: "pdf" or "requirements"</div>
                <div>• Try: "john" or "project manager"</div>
            </div>
        </div>
    </div>
    
    <!-- Smart Filters Demo -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Smart Filters</h3>
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-3">
                Smart filters provide role-aware presets and deep filtering capabilities:
            </p>
            <ul class="text-sm text-gray-600 space-y-1 ml-4">
                <li>• One-tap focus presets (My Overdue, At-Risk Projects, Due This Week)</li>
                <li>• Deep filters with multiple data types (select, range, date)</li>
                <li>• Saved filter views for quick access</li>
                <li>• Role-aware presets (different for admin vs tenant)</li>
            </ul>
        </div>
        
        <!-- Smart Filters Component -->
        @include('components.smart-filters')
        
        <!-- Filter Examples -->
        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Filter Examples:</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-600">
                <div>• My Overdue Tasks</div>
                <div>• At-Risk Projects</div>
                <div>• Due This Week</div>
                <div>• High Priority Items</div>
            </div>
        </div>
    </div>
    
    <!-- Analysis & Export Demo -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Analysis Drawer -->
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Analysis Drawer</h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-3">
                    Right drawer with charts and insights for current filter context:
                </p>
                <ul class="text-sm text-gray-600 space-y-1 ml-4">
                    <li>• Interactive charts (pie, bar, line)</li>
                    <li>• Key metrics with trends</li>
                    <li>• AI-generated insights</li>
                    <li>• Export analysis to PDF/Excel</li>
                </ul>
            </div>
            
            <!-- Analysis Drawer Component -->
            @include('components.analysis-drawer')
        </div>
        
        <!-- Export Component -->
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Export System</h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-3">
                    Export data with role and tenant awareness:
                </p>
                <ul class="text-sm text-gray-600 space-y-1 ml-4">
                    <li>• Multiple formats (CSV, Excel, PDF)</li>
                    <li>• Column selection and customization</li>
                    <li>• Filter-aware exports</li>
                    <li>• Export history and management</li>
                </ul>
            </div>
            
            <!-- Export Component -->
            @include('components.export-component')
        </div>
    </div>
    
    <!-- Features Overview -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Smart Tools Features</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Intelligent Search -->
            <div class="space-y-2">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-search text-blue-600"></i>
                    <h4 class="font-semibold text-gray-900">Intelligent Search</h4>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Fuzzy matching algorithm</li>
                    <li>• Recent searches history</li>
                    <li>• Autocomplete suggestions</li>
                    <li>• Role-aware results</li>
                    <li>• Keyboard shortcuts</li>
                </ul>
            </div>
            
            <!-- Smart Filters -->
            <div class="space-y-2">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-filter text-green-600"></i>
                    <h4 class="font-semibold text-gray-900">Smart Filters</h4>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• One-tap focus presets</li>
                    <li>• Deep filter capabilities</li>
                    <li>• Saved filter views</li>
                    <li>• Role-aware presets</li>
                    <li>• Real-time filtering</li>
                </ul>
            </div>
            
            <!-- Analysis -->
            <div class="space-y-2">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-chart-bar text-purple-600"></i>
                    <h4 class="font-semibold text-gray-900">Analysis</h4>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Interactive charts</li>
                    <li>• Key metrics tracking</li>
                    <li>• AI-generated insights</li>
                    <li>• Context-aware analysis</li>
                    <li>• Export capabilities</li>
                </ul>
            </div>
            
            <!-- Export -->
            <div class="space-y-2">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-download text-orange-600"></i>
                    <h4 class="font-semibold text-gray-900">Export</h4>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Multiple formats</li>
                    <li>• Column customization</li>
                    <li>• Filter-aware exports</li>
                    <li>• Export history</li>
                    <li>• Role-based permissions</li>
                </ul>
            </div>
            
            <!-- Performance -->
            <div class="space-y-2">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-tachometer-alt text-red-600"></i>
                    <h4 class="font-semibold text-gray-900">Performance</h4>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Cached search results</li>
                    <li>• Debounced input</li>
                    <li>• Lazy loading</li>
                    <li>• Optimized queries</li>
                    <li>• Background processing</li>
                </ul>
            </div>
            
            <!-- Accessibility -->
            <div class="space-y-2">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-universal-access text-indigo-600"></i>
                    <h4 class="font-semibold text-gray-900">Accessibility</h4>
                </div>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Keyboard navigation</li>
                    <li>• Screen reader support</li>
                    <li>• ARIA labels</li>
                    <li>• Focus management</li>
                    <li>• WCAG 2.1 AA compliance</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- API Endpoints -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">API Endpoints</h3>
        
        <div class="space-y-4">
            <!-- Search API -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Search API</h4>
                <div class="bg-gray-50 rounded-lg p-3 font-mono text-sm">
                    <div>POST /api/universal-frame/search</div>
                    <div>GET /api/universal-frame/search/suggestions</div>
                    <div>GET /api/universal-frame/search/recent</div>
                </div>
            </div>
            
            <!-- Filter API -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Filter API</h4>
                <div class="bg-gray-50 rounded-lg p-3 font-mono text-sm">
                    <div>GET /api/universal-frame/filters/presets</div>
                    <div>GET /api/universal-frame/filters/deep</div>
                    <div>GET /api/universal-frame/filters/saved-views</div>
                </div>
            </div>
            
            <!-- Analysis API -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Analysis API</h4>
                <div class="bg-gray-50 rounded-lg p-3 font-mono text-sm">
                    <div>POST /api/universal-frame/analysis</div>
                    <div>GET /api/universal-frame/analysis/{context}</div>
                    <div>GET /api/universal-frame/analysis/{context}/metrics</div>
                </div>
            </div>
            
            <!-- Export API -->
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Export API</h4>
                <div class="bg-gray-50 rounded-lg p-3 font-mono text-sm">
                    <div>POST /api/universal-frame/export</div>
                    <div>GET /api/universal-frame/export/history</div>
                    <div>DELETE /api/universal-frame/export/{filename}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
