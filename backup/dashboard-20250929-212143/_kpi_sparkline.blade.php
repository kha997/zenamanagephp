{{-- 
    KPI Sparkline Component 
    Creates inline SVG sparkline graphics for KPI cards
    Similar to Users/Security dashboard sparklines
    
    @param array $data - Array of numeric values 
    @param string $color - Color for the sparkline (default from semantic colors)
    @param string $id - Unique ID for SVG element
--}}

@php
    // Default colors by semantic meaning
    $colors = [
        'users' => '#3B82F6',      // blue-500
        'tenants' => '#10B981',    // emerald-500  
        'errors' => '#EF4444',     // red-500
        'queue' => '#F59E0B',      // amber-500
        'storage' => '#8B5CF6',    // violet-500
        'active' => '#10B981'       // emerald-500
    ];
    
    $sparklineColor = $color ?? ($colors[$type] ?? '#6B7280');
    $svgId = $id ?? 'sparkline-' . Str::random(8);
    
    // Process data: ensure it's array and has minimum points
    $sparklineData = is_array($data) ? $data : [];
    if (empty($sparklineData)) {
        $sparklineData = [0, 0, 0, 0, 0, 0, 0]; // Default flat line
    }
    
    // Normalize data - ensure minimum 7 points
    while (count($sparklineData) < 7) {
        $sparklineData = array_merge($sparklineData, $sparklineData);
    }
    $sparklineData = array_slice($sparklineData, 0, 7);
    
    // Calculate SVG dimensions (80x24 as requested)
    $svgWidth = 80;
    $svgHeight = 24;
    $margin = 2;
    
    // Calculate path coordinates
    $chartWidth = $svgWidth - ($margin * 2);
    $chartHeight = $svgHeight - ($margin * 2);
    
    // Find min/max for scaling
    $minValue = min($sparklineData);
    $maxValue = max($sparklineData);
    $valueRange = ($maxValue - $minValue) ?: 1; // Avoid division by zero
    
    // Generate SVG path points
    $pathPoints = [];
    $areaPoints = [];
    
    foreach ($sparklineData as $index => $value) {
        $x = $margin + ($index / (count($sparklineData) - 1)) * $chartWidth;
        $y = $margin + (($maxValue - $value) / $valueRange) * $chartHeight;
        
        // Ensure y is within bounds
        $y = max($margin, min($svgHeight - $margin, $y));
        
        $pathPoints[] = round($x, 1) . ',' . round($y, 1);
        $areaPoints[] = round($x, 1) . ',' . round($y, 1);
    }
    
    // Complete area polygon (fill to bottom)
    $areaPoints[] = ($margin + $chartWidth) . ',' . ($svgHeight - $margin);
    $areaPoints[] = $margin . ',' . ($svgHeight - $margin);
    
    $pathData = 'M' . implode(' L', $pathPoints);
    $areaData = implode(' ', $areaPoints);
    
@endphp

<svg 
    id="{{ $svgId }}" 
    width="{{ $svgWidth }}" 
    height="{{ $svgHeight }}" 
    viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
    class="sparkline-svg"
    aria-hidden="true">
    
    {{-- Fill area under the line --}}
    <polygon 
        points="{{ $areaData }}"
        fill="{{ $sparklineColor }}"
        fill-opacity="0.2"
        opacity="0.6"/>
    
    {{-- Main sparkline --}}
    <path 
        d="{{ $pathData }}"
        fill="none"
        stroke="{{ $sparklineColor }}"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"/>
    
    {{-- Data points (small dots for emphasis) --}}
    @foreach($sparklineData as $index => $value)
        @php
            $x = $margin + ($index / (count($sparklineData) - 1)) * $chartWidth;
            $y = $margin + (($maxValue - $value) / $valueRange) * $chartHeight;
            // Show only endpoints and midpoints to avoid clutter
            $showPoint = in_array($index, [0, floor(count($sparklineData)/2), count($sparklineData)-1]);
        @endphp
        @if($showPoint)
            <circle 
                cx="{{ round($x, 1) }}" 
                cy="{{ round($y, 1) }}" 
                r="1" 
                fill="{{ $sparklineColor }}"
                opacity="0.8"/>
        @endif
    @endforeach
</svg>

{{-- Hidden screen reader description --}}
<span class="sr-only">
    @if($type === 'users')
        User count trend: {{ $sparklineData[0] }} to {{ end($sparklineData) }}, {{ count($sparklineData) }} data points
    @elseif($type === 'tenants')
        Tenant count trend: {{ $sparklineData[0] }} to {{ end($sparklineData) }}, {{ count($sparklineData) }} data points
    @elseif($type === 'errors')
        Error rate trend: {{ $sparklineData[0] }} to {{ end($sparklineData) }}, {{ count($sparklineData) }} data points
    @elseif($type === 'queue')
        Queue jobs trend: {{ $sparklineData[0] }} to {{ end($sparklineData) }}, {{ count($sparklineData) }} data points
    @elseif($type === 'storage')
        Storage usage trend: {{ $sparklineData[0] }} to {{ end($sparklineData) }}, {{ count($sparklineData) }} data points
    @else
        Data trend: {{ $sparklineData[0] }} to {{ end($sparklineData) }}, {{ count($sparklineData) }} data points
    @endif
</span>

{{-- Optional CSS for sparkline animations --}}
<style scoped>
.sparkline-svg {
    transition: opacity 0.2s ease;
}

.sparkline-svg:hover {
    opacity: 0.8;
}

/* Pulse animation for active data points */
@keyframes pulse-sparkline {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 1; }
}

.sparkline-svg circle {
    animation: pulse-sparkline 2s ease-in-out infinite;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .sparkline-svg {
        stroke-width: 2;
        opacity: 1;
    }
}

/* Reduced motion override */
@media (prefers-reduced-motion: reduce) {
    .sparkline-svg circle {
        animation: none;
    }
}
</style>
