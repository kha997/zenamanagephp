{{-- Mobile Floating Action Button --}}
{{-- FAB for mobile devices with quick actions --}}

<div class="fixed bottom-6 right-6 z-mobile-fab sm:hidden">
    <div x-data="mobileFAB()" class="relative">
        <!-- Main FAB Button -->
        <button @click="toggleMenu()" 
                class="w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i class="fas fa-plus text-xl" :class="{ 'rotate-45': menuOpen }"></i>
        </button>
        
        <!-- Action Menu -->
        <div x-show="menuOpen" 
             @click.away="menuOpen = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="absolute bottom-16 right-0 space-y-2">
            
            @foreach($actions as $action)
            <div class="flex items-center space-x-3">
                <a href="{{ $action['url'] }}" 
                   class="bg-white text-gray-700 px-4 py-2 rounded-lg shadow-lg text-sm font-medium hover:bg-gray-50 transition-colors whitespace-nowrap">
                    {{ $action['label'] }}
                </a>
                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="{{ $action['icon'] }} text-gray-600"></i>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Alpine.js Component -->
<script>
function mobileFAB() {
    return {
        menuOpen: false,
        toggleMenu() {
            this.menuOpen = !this.menuOpen;
        }
    }
}
</script>
