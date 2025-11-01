{{-- Congrats Component --}}
{{-- Celebration component for task completion and achievements --}}

@php
    $isEnabled = app(\App\Services\FeatureFlagService::class)->isEnabled('ui.enable_rewards');
@endphp

@if($isEnabled)
<div class="congrats-component" x-data="rewards()">
    <!-- Rewards Toggle Button -->
    <button @click="toggle()" 
            data-rewards-toggle
            :class="isActive ? 'active rewards-active' : ''"
            :aria-pressed="isActive"
            :title="toggleText"
            class="flex items-center space-x-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500"
            :class="isActive ? 
                'bg-green-100 text-green-700 border border-green-300 hover:bg-green-200' : 
                'bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200'">
        
        <i :class="toggleIcon" class="text-sm"></i>
        <span class="hidden sm:inline">{{ __('app.rewards') }}</span>
        
        <span class="sm:hidden" x-show="isActive">{{ __('app.on') }}</span>
    </button>
    
    <!-- Rewards Status Indicator -->
    <div x-show="isActive" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 p-3 z-50">
        
        <div class="flex items-center space-x-2 mb-2">
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-sm font-medium text-gray-900">{{ __('app.rewards_active') }}</span>
        </div>
        
        <p class="text-xs text-gray-600 mb-3">
            {{ __('app.rewards_description') }}
        </p>
        
        <div class="flex items-center justify-between">
            <button @click="toggle()" 
                    class="text-xs text-red-600 hover:text-red-800 font-medium">
                {{ __('app.disable_rewards') }}
            </button>
            
            <div class="flex items-center space-x-1 text-xs text-gray-500">
                <i class="fas fa-gift"></i>
                <span>{{ __('app.celebrations') }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Congrats Message Template (Hidden) -->
<template id="congrats-template">
    <div class="congrats-message-overlay">
        <div class="congrats-message">
            <div class="congrats-icon">🎉</div>
            <h2 class="congrats-title">{{ __('app.congratulations') }}</h2>
            <p class="congrats-message-text">{{ __('app.great_job_task_completed') }}</p>
            <p class="congrats-subtitle">{{ __('app.keep_up_great_work') }}</p>
        </div>
    </div>
</template>

<!-- Congrats Styles -->
<style>
.congrats-component {
    position: relative;
}

.congrats-component button {
    transition: all 0.2s ease-in-out;
}

.congrats-component button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.congrats-component button.active {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

/* Congrats Message Styles */
.congrats-message-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: fadeIn 0.3s ease-in;
}

.congrats-message {
    background: white;
    padding: 2rem;
    border-radius: 1rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    animation: slideInUp 0.5s ease-out;
    max-width: 400px;
    margin: 1rem;
}

.congrats-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: bounce 1s ease-in-out infinite;
}

.congrats-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 0.5rem;
}

.congrats-message-text {
    font-size: 1.1rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.congrats-subtitle {
    font-size: 0.9rem;
    color: #888;
    font-style: italic;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInUp {
    from { 
        opacity: 0;
        transform: translateY(50px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
}

/* Confetti Canvas Styles */
#confetti-canvas {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
    display: none;
}

/* Task Completion Animation */
.task-completed {
    animation: taskCompleted 0.6s ease-out;
}

@keyframes taskCompleted {
    0% { 
        transform: scale(1);
        background-color: transparent;
    }
    50% { 
        transform: scale(1.05);
        background-color: rgba(34, 197, 94, 0.1);
    }
    100% { 
        transform: scale(1);
        background-color: transparent;
    }
}

/* Success Badge Animation */
.success-badge {
    animation: successBadge 0.8s ease-out;
}

@keyframes successBadge {
    0% { 
        opacity: 0;
        transform: scale(0.5) rotate(-10deg);
    }
    50% { 
        opacity: 1;
        transform: scale(1.1) rotate(5deg);
    }
    100% { 
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

/* Progress Bar Completion Animation */
.progress-complete {
    animation: progressComplete 1s ease-out;
}

@keyframes progressComplete {
    0% { 
        width: var(--progress-before);
    }
    100% { 
        width: 100%;
    }
}
</style>

<!-- Confetti Canvas -->
<canvas id="confetti-canvas"></canvas>

@else
<!-- Feature Disabled Message -->
<div class="rewards-disabled text-xs text-gray-500 px-2 py-1">
    <i class="fas fa-lock mr-1"></i>
    {{ __('app.feature_disabled') }}
</div>
@endif
