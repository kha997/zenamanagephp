@props(['breadcrumbs' => null])

@php
    $breadcrumbs = $breadcrumbs ?? \App\Services\BreadcrumbService::generate();
@endphp

@if(!empty($breadcrumbs))
<nav class="flex" aria-label="Breadcrumb">
    <ol class="flex items-center space-x-2">
        @foreach($breadcrumbs as $index => $breadcrumb)
            @if($index > 0)
                <li class="flex items-center">
                    <svg class="w-4 h-4 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </li>
            @endif
            
            <li class="flex items-center">
                @if($breadcrumb['active'])
                    <span class="text-gray-500 font-medium">{{ $breadcrumb['title'] }}</span>
                @else
                    <a href="{{ $breadcrumb['url'] }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        {{ $breadcrumb['title'] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif