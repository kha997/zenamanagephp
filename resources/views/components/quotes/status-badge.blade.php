{{--
    Quote Status Badge Component
    
    Displays a colored badge for quote status
    
    Usage:
    <x-quotes.status-badge :status="$quote->status" />
    
    Props:
    - status: string (draft, sent, viewed, accepted, rejected, expired)
--}}

@props(['status' => 'draft'])

@php
    $statusConfig = [
        'draft' => [
            'class' => 'bg-gray-100 text-gray-800',
            'icon' => 'fas fa-edit',
            'label' => __('quotes.draft')
        ],
        'sent' => [
            'class' => 'bg-blue-100 text-blue-800',
            'icon' => 'fas fa-paper-plane',
            'label' => __('quotes.sent')
        ],
        'viewed' => [
            'class' => 'bg-yellow-100 text-yellow-800',
            'icon' => 'fas fa-eye',
            'label' => __('quotes.viewed')
        ],
        'accepted' => [
            'class' => 'bg-green-100 text-green-800',
            'icon' => 'fas fa-check-circle',
            'label' => __('quotes.accepted')
        ],
        'rejected' => [
            'class' => 'bg-red-100 text-red-800',
            'icon' => 'fas fa-times-circle',
            'label' => __('quotes.rejected')
        ],
        'expired' => [
            'class' => 'bg-gray-100 text-gray-800',
            'icon' => 'fas fa-clock',
            'label' => __('quotes.expired')
        ],
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['draft'];
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config['class'] }}">
    <i class="{{ $config['icon'] }} mr-1"></i>
    {{ $config['label'] }}
</span>
