@extends('emails.layout')

@section('title', __('notifications.quote_sent_title'))

@section('content')
<div class="email-container">
    <div class="email-header">
        <h1>{{ __('notifications.quote_sent_title') }}</h1>
    </div>
    
    <div class="email-body">
        <p>{{ __('notifications.quote_sent_greeting', ['name' => $client->name]) }}</p>
        
        <div class="quote-info">
            <h2>{{ __('notifications.quote_details') }}</h2>
            <ul>
                <li><strong>{{ __('notifications.quote_number') }}:</strong> {{ $quote->quote_number }}</li>
                <li><strong>{{ __('notifications.project_type') }}:</strong> {{ $quote->project_type }}</li>
                <li><strong>{{ __('notifications.total_amount') }}:</strong> {{ number_format($quote->total_amount, 0, ',', '.') }} VND</li>
                <li><strong>{{ __('notifications.valid_until') }}:</strong> {{ $quote->valid_until->format('d/m/Y') }}</li>
            </ul>
        </div>
        
        <div class="email-actions">
            <a href="{{ route('app.quotes.show', $quote->id) }}" class="btn btn-primary">
                {{ __('notifications.view_quote') }}
            </a>
            <a href="{{ route('app.quotes.download', $quote->id) }}" class="btn btn-secondary">
                {{ __('notifications.download_pdf') }}
            </a>
        </div>
        
        <p class="email-footer">
            {{ __('notifications.email_footer') }}
        </p>
    </div>
</div>
@endsection
