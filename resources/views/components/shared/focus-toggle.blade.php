{{-- Focus Mode Toggle Component --}}
{{-- Toggle button for entering/exiting Focus Mode --}}

@php
    $isEnabled = app(\App\Services\FeatureFlagService::class)->isEnabled('ui.enable_focus_mode');
@endphp

@if($isEnabled)
<div class="focus-mode-toggle" x-data="focusMode()">
    <button @click="toggle()" 
            data-focus-mode-toggle
            :class="isActive ? 'active focus-mode-active' : ''"
            :aria-pressed="isActive"
            :title="toggleText"
            class="flex items-center space-x-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="isActive ? 
                'bg-blue-100 text-blue-700 border border-blue-300 hover:bg-blue-200' : 
                'bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200'">
        
        <i :class="toggleIcon" class="text-sm"></i>
        <span class="hidden sm:inline">{{ __('app.focus_mode') }}</span>
        
        <span class="sm:hidden" x-show="isActive">{{ __('app.focus') }}</span>
    </button>
    
    <!-- Focus Mode Status Indicator -->
    <div x-show="isActive" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.away="isActive = false"
         class="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 p-3 z-focus-mode">
        
        <div class="flex items-center space-x-2 mb-2">
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-sm font-medium text-gray-900">{{ __('app.focus_mode_active') }}</span>
        </div>
        
        <p class="text-xs text-gray-600 mb-3">
            {{ __('app.focus_mode_description') }}
        </p>
        
        <div class="flex items-center justify-between">
            <button @click="toggle()" 
                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                {{ __('app.exit_focus_mode') }}
            </button>
            
            <div class="flex items-center space-x-1 text-xs text-gray-500">
                <i class="fas fa-info-circle"></i>
                <span>{{ __('app.minimal_ui') }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Focus Mode Styles -->
<style>
.focus-mode-toggle {
    position: relative;
}

.focus-mode-toggle button {
    transition: all 0.2s ease-in-out;
}

.focus-mode-toggle button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.focus-mode-toggle button.active {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
    100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
}

/* Focus Mode Global Styles */
.focus-mode {
    --focus-mode-spacing: 2rem;
    --focus-mode-font-size: 1.1rem;
    --focus-mode-line-height: 1.6;
}

.focus-mode .main-content {
    padding: var(--focus-mode-spacing);
    max-width: 100%;
}

.focus-mode .content-area {
    font-size: var(--focus-mode-font-size);
    line-height: var(--focus-mode-line-height);
}

.focus-mode .card, .focus-mode .panel {
    margin-bottom: var(--focus-mode-spacing);
    padding: var(--focus-mode-spacing);
}

.focus-mode .sidebar.collapsed {
    width: 60px;
    min-width: 60px;
}

.focus-mode .sidebar.focus-mode-collapsed {
    transform: translateX(-100%);
    width: 0;
    min-width: 0;
}

.focus-mode .focus-mode-hidden {
    display: none !important;
}

.focus-mode .focus-mode-main {
    width: 100%;
    max-width: none;
}

/* Minimal Theme */
.minimal-theme {
    --minimal-bg: #fafafa;
    --minimal-text: #333;
    --minimal-border: #e5e5e5;
}

.minimal-theme body {
    background-color: var(--minimal-bg);
    color: var(--minimal-text);
}

.minimal-theme .card, .minimal-theme .panel {
    background: white;
    border: 1px solid var(--minimal-border);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.minimal-theme .btn-primary {
    background: #2563eb;
    border-color: #2563eb;
}

.minimal-theme .btn-secondary {
    background: #6b7280;
    border-color: #6b7280;
}
</style>
@else
<!-- Feature Disabled Message -->
<div class="focus-mode-disabled text-xs text-gray-500 px-2 py-1">
    <i class="fas fa-lock mr-1"></i>
    {{ __('app.feature_disabled') }}
</div>
@endif
