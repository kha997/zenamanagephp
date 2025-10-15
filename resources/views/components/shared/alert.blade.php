{{-- Shared Alert Component --}}
@props([
    'type' => 'info', // info, success, warning, error
    'title' => null,
    'message' => null,
    'dismissible' => true
])

@php
$alertClasses = [
    'info' => 'bg-blue-50 border-blue-200 text-blue-800',
    'success' => 'bg-green-50 border-green-200 text-green-800',
    'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
    'error' => 'bg-red-50 border-red-200 text-red-800'
];

$iconClasses = [
    'info' => 'fas fa-info-circle text-blue-400',
    'success' => 'fas fa-check-circle text-green-400',
    'warning' => 'fas fa-exclamation-triangle text-yellow-400',
    'error' => 'fas fa-exclamation-circle text-red-400'
];
@endphp

<div class="rounded-md border p-4 {{ $alertClasses[$type] }}" role="alert">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="{{ $iconClasses[$type] }}"></i>
        </div>
        <div class="ml-3 flex-1">
            @if($title)
            <h3 class="text-sm font-medium">{{ $title }}</h3>
            @endif
            @if($message)
            <div class="mt-2 text-sm">
                <p>{{ $message }}</p>
            </div>
            @endif
        </div>
        @if($dismissible)
        <div class="ml-auto pl-3">
            <div class="-mx-1.5 -my-1.5">
                <button type="button" class="inline-flex rounded-md p-1.5 hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-blue-50 focus:ring-blue-600" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                    <span class="sr-only">{{ __('common.dismiss') }}</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        @endif
    </div>
</div>
