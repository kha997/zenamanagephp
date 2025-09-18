{{-- Breadcrumb Navigation Component --}}
@props(['items' => []])

<nav class="zena-breadcrumb" aria-label="Breadcrumb">
    <ol class="zena-breadcrumb-list">
        @foreach($items as $index => $item)
            <li class="zena-breadcrumb-item">
                @if($index === count($items) - 1)
                    {{-- Current page (last item) --}}
                    <span class="zena-breadcrumb-current" aria-current="page">
                        @if(isset($item['icon']))
                            <i class="{{ $item['icon'] }} mr-2"></i>
                        @endif
                        {{ $item['label'] }}
                    </span>
                @else
                    {{-- Link to other pages --}}
                    <a href="{{ $item['url'] }}" class="zena-breadcrumb-link">
                        @if(isset($item['icon']))
                            <i class="{{ $item['icon'] }} mr-2"></i>
                        @endif
                        {{ $item['label'] }}
                    </a>
                @endif
            </li>
            
            @if($index < count($items) - 1)
                {{-- Separator --}}
                <li class="zena-breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
