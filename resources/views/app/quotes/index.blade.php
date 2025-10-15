@extends('layouts.app-layout')

@section('title', __('quotes.title'))

@section('kpi-strip')
<x-kpi.strip :kpis="$kpiStats" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('quotes.title') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('quotes.subtitle') }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('app.quotes.create') }}" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    {{ __('quotes.create_quote') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('quotes.total_quotes') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('quotes.accepted') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['accepted'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('quotes.expiring_soon') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['expiring_soon'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('quotes.total_value') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">${{ number_format($stats['total_value'] ?? 0, 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('quotes.search') }}
                    </label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="{{ __('quotes.search_placeholder') }}"
                           class="form-input w-full">
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('quotes.status') }}
                    </label>
                    <select id="status" 
                            name="status" 
                            class="form-select w-full">
                        <option value="">{{ __('quotes.all_statuses') }}</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>
                            {{ __('quotes.draft') }}
                        </option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>
                            {{ __('quotes.sent') }}
                        </option>
                        <option value="viewed" {{ request('status') === 'viewed' ? 'selected' : '' }}>
                            {{ __('quotes.viewed') }}
                        </option>
                        <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>
                            {{ __('quotes.accepted') }}
                        </option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>
                            {{ __('quotes.rejected') }}
                        </option>
                        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>
                            {{ __('quotes.expired') }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('quotes.type') }}
                    </label>
                    <select id="type" 
                            name="type" 
                            class="form-select w-full">
                        <option value="">{{ __('quotes.all_types') }}</option>
                        <option value="design" {{ request('type') === 'design' ? 'selected' : '' }}>
                            {{ __('quotes.design') }}
                        </option>
                        <option value="construction" {{ request('type') === 'construction' ? 'selected' : '' }}>
                            {{ __('quotes.construction') }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('quotes.client') }}
                    </label>
                    <select id="client_id" 
                            name="client_id" 
                            class="form-select w-full">
                        <option value="">{{ __('quotes.all_clients') }}</option>
                        @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                            {{ $client->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                            class="btn btn-primary w-full">
                        <i class="fas fa-search mr-2"></i>
                        {{ __('quotes.filter') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quotes Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">{{ __('quotes.quote_list') }}</h3>
        </div>

        @if($quotes->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.quote') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.client') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.status') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.type') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.amount') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.valid_until') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('quotes.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($quotes as $quote)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $quote->title }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ __('quotes.created') }} {{ $quote->created_at->format('M d, Y') }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $quote->client->name }}</div>
                                @if($quote->client->company)
                                <div class="text-sm text-gray-500">{{ $quote->client->company }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-quotes.status-badge :status="$quote->status" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($quote->type === 'design') bg-purple-100 text-purple-800
                                    @else bg-orange-100 text-orange-800
                                    @endif">
                                    {{ __('quotes.' . $quote->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ number_format($quote->final_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $quote->valid_until->format('M d, Y') }}
                                @if($quote->isExpired())
                                    <span class="text-red-500 text-xs">({{ __('quotes.expired') }})</span>
                                @elseif($quote->expiringSoon())
                                    <span class="text-yellow-500 text-xs">({{ __('quotes.expiring_soon') }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('app.quotes.show', $quote) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        {{ __('quotes.view') }}
                                    </a>
                                    @if($quote->canBeSent())
                                    <form action="{{ route('app.quotes.send', $quote) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" 
                                                class="text-green-600 hover:text-green-900">
                                            {{ __('quotes.send') }}
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $quotes->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fas fa-file-invoice text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('quotes.no_quotes') }}</h3>
                <p class="text-gray-500 mb-6">{{ __('quotes.no_quotes_description') }}</p>
                <a href="{{ route('app.quotes.create') }}" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    {{ __('quotes.create_first_quote') }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
