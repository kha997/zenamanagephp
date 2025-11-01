{{-- Shared Pagination Component --}}
@props(['paginator' => null])

@if($paginator && $paginator->hasPages())
<div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
    <div class="flex-1 flex justify-between sm:hidden">
        @if($paginator->onFirstPage())
        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white cursor-not-allowed">
            {{ __('pagination.previous') }}
        </span>
        @else
        <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            {{ __('pagination.previous') }}
        </a>
        @endif

        @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            {{ __('pagination.next') }}
        </a>
        @else
        <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white cursor-not-allowed">
            {{ __('pagination.next') }}
        </span>
        @endif
    </div>
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-700">
                {{ __('pagination.showing') }}
                <span class="font-medium">{{ $paginator->firstItem() }}</span>
                {{ __('pagination.to') }}
                <span class="font-medium">{{ $paginator->lastItem() }}</span>
                {{ __('pagination.of') }}
                <span class="font-medium">{{ $paginator->total() }}</span>
                {{ __('pagination.results') }}
            </p>
        </div>
        <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                {{-- Previous Page Link --}}
                @if($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 cursor-not-allowed">
                    <span class="sr-only">{{ __('pagination.previous') }}</span>
                    <i class="fas fa-chevron-left h-5 w-5"></i>
                </span>
                @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">{{ __('pagination.previous') }}</span>
                    <i class="fas fa-chevron-left h-5 w-5"></i>
                </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if($page == $paginator->currentPage())
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">
                    {{ $page }}
                </span>
                @else
                <a href="{{ $url }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    {{ $page }}
                </a>
                @endif
                @endforeach

                {{-- Next Page Link --}}
                @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">{{ __('pagination.next') }}</span>
                    <i class="fas fa-chevron-right h-5 w-5"></i>
                </a>
                @else
                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 cursor-not-allowed">
                    <span class="sr-only">{{ __('pagination.next') }}</span>
                    <i class="fas fa-chevron-right h-5 w-5"></i>
                </span>
                @endif
            </nav>
        </div>
    </div>
</div>
@endif
