{{-- Mobile Hamburger Menu Component --}}
{{-- Responsive hamburger menu for mobile navigation --}}

@props([
    'open' => false,
    'variant' => 'default', // 'default', 'minimal', 'overlay'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
])

@php
    $sizeClasses = [
        'sm' => 'w-6 h-6',
        'md' => 'w-8 h-8',
        'lg' => 'w-10 h-10'
    ];
    
    $lineClasses = [
        'sm' => 'h-0.5',
        'md' => 'h-0.5',
        'lg' => 'h-1'
    ];
@endphp

<button class="hamburger-menu {{ $sizeClasses[$size] }} flex flex-col justify-center items-center focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md"
        x-data="hamburgerMenu()"
        :class="{ 'active': open }"
        @click="toggle()"
        aria-label="Toggle mobile menu"
        aria-expanded="false"
        :aria-expanded="open">
    
    {{-- Hamburger Lines --}}
    <span class="hamburger-line {{ $lineClasses[$size] }} w-full bg-gray-600 transition-all duration-300 ease-in-out transform"
          :class="{ 'rotate-45 translate-y-1.5': open }"></span>
    
    <span class="hamburger-line {{ $lineClasses[$size] }} w-full bg-gray-600 transition-all duration-300 ease-in-out mt-1"
          :class="{ 'opacity-0': open }"></span>
    
    <span class="hamburger-line {{ $lineClasses[$size] }} w-full bg-gray-600 transition-all duration-300 ease-in-out mt-1 transform"
          :class="{ '-rotate-45 -translate-y-1.5': open }"></span>
</button>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('hamburgerMenu', () => ({
        open: {{ $open ? 'true' : 'false' }},
        
        toggle() {
            this.open = !this.open;
            this.$dispatch('hamburger-toggle', { open: this.open });
        }
    }));
});
</script>
@endpush
