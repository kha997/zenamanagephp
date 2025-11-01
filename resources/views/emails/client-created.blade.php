@extends('emails.layout')

@section('title', __('notifications.client_created_title'))

@section('content')
<div class="email-container">
    <div class="email-header">
        <h1>{{ __('notifications.client_created_title') }}</h1>
    </div>
    
    <div class="email-body">
        <p>{{ __('notifications.client_created_greeting', ['name' => $user->name]) }}</p>
        
        <div class="client-info">
            <h2>{{ __('notifications.client_details') }}</h2>
            <ul>
                <li><strong>{{ __('notifications.client_name') }}:</strong> {{ $client->name }}</li>
                <li><strong>{{ __('notifications.client_email') }}:</strong> {{ $client->email }}</li>
                <li><strong>{{ __('notifications.client_phone') }}:</strong> {{ $client->phone }}</li>
                <li><strong>{{ __('notifications.client_type') }}:</strong> {{ $client->type === 'potential' ? __('notifications.potential_client') : __('notifications.signed_client') }}</li>
                <li><strong>{{ __('notifications.created_by') }}:</strong> {{ $client->createdBy->name }}</li>
            </ul>
        </div>
        
        <div class="email-actions">
            <a href="{{ route('app.clients.show', $client->id) }}" class="btn btn-primary">
                {{ __('notifications.view_client') }}
            </a>
            <a href="{{ route('app.clients.index') }}" class="btn btn-secondary">
                {{ __('notifications.view_all_clients') }}
            </a>
        </div>
        
        <p class="email-footer">
            {{ __('notifications.email_footer') }}
        </p>
    </div>
</div>
@endsection
