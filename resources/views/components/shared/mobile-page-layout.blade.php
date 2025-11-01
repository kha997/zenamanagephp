{{-- Mobile-First Page Layout Component --}}
{{-- Responsive page layout with mobile optimization --}}

<div class="min-h-screen bg-gray-50">
    <!-- Mobile-First Content Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        <!-- Page Header - Responsive -->
        <div class="mb-6 sm:mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 truncate">
                        {{ $title }}
                    </h1>
                    @if(isset($subtitle))
                    <p class="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600">
                        {{ $subtitle }}
                    </p>
                    @endif
                </div>
                
                @if(isset($actions))
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    {{ $actions }}
                </div>
                @endif
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="space-y-6 sm:space-y-8">
            {{ $slot }}
        </div>
    </div>
</div>

<!-- Mobile-Specific Styles -->
<style>
@media (max-width: 640px) {
    /* Mobile optimizations */
    .mobile-stack {
        @apply flex-col space-y-2;
    }
    
    .mobile-full {
        @apply w-full;
    }
    
    .mobile-text-sm {
        @apply text-sm;
    }
    
    .mobile-p-4 {
        @apply p-4;
    }
    
    .mobile-hidden {
        @apply hidden;
    }
}

@media (min-width: 641px) {
    .desktop-show {
        @apply block;
    }
}

/* Touch-friendly buttons */
@media (hover: none) and (pointer: coarse) {
    .touch-target {
        @apply min-h-12 min-w-12;
    }
}
</style>
