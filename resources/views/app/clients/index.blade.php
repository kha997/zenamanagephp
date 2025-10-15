@extends('layouts.app-layout')

@section('title', __('clients.title'))

@section('kpi-strip')
<x-kpi.strip :kpis="$kpis" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('clients.title') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('clients.subtitle') }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('clients.create') }}" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    {{ __('clients.create_client') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('clients.total_clients') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-plus text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('clients.leads') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['leads'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('clients.prospects') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['prospects'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-star text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('clients.customers') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['customers'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-slash text-gray-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">{{ __('clients.inactive') }}</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats['inactive'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('clients.search') }}
                    </label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="{{ __('clients.search_placeholder') }}"
                           class="form-input w-full">
                </div>

                <div>
                    <label for="lifecycle_stage" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('clients.lifecycle_stage') }}
                    </label>
                    <select id="lifecycle_stage" 
                            name="lifecycle_stage" 
                            class="form-select w-full">
                        <option value="">{{ __('clients.all_stages') }}</option>
                        <option value="lead" {{ request('lifecycle_stage') === 'lead' ? 'selected' : '' }}>
                            {{ __('clients.lead') }}
                        </option>
                        <option value="prospect" {{ request('lifecycle_stage') === 'prospect' ? 'selected' : '' }}>
                            {{ __('clients.prospect') }}
                        </option>
                        <option value="customer" {{ request('lifecycle_stage') === 'customer' ? 'selected' : '' }}>
                            {{ __('clients.customer') }}
                        </option>
                        <option value="inactive" {{ request('lifecycle_stage') === 'inactive' ? 'selected' : '' }}>
                            {{ __('clients.inactive') }}
                        </option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('clients.status') }}
                    </label>
                    <select id="status" 
                            name="status" 
                            class="form-select w-full">
                        <option value="">{{ __('clients.all_statuses') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>
                            {{ __('clients.active') }}
                        </option>
                        <option value="customers" {{ request('status') === 'customers' ? 'selected' : '' }}>
                            {{ __('clients.customers') }}
                        </option>
                        <option value="prospects" {{ request('status') === 'prospects' ? 'selected' : '' }}>
                            {{ __('clients.prospects') }}
                        </option>
                        <option value="leads" {{ request('status') === 'leads' ? 'selected' : '' }}>
                            {{ __('clients.leads') }}
                        </option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>
                            {{ __('clients.inactive') }}
                        </option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                            class="btn btn-primary w-full">
                        <i class="fas fa-search mr-2"></i>
                        {{ __('clients.filter') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">{{ __('clients.client_list') }}</h3>
        </div>

        @if($clients->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('clients.client') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('clients.contact') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('clients.lifecycle_stage') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('clients.quotes') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('clients.created') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('clients.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($clients as $client)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $client->name }}
                                        </div>
                                        @if($client->company)
                                        <div class="text-sm text-gray-500">
                                            {{ $client->company }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $client->email }}</div>
                                @if($client->phone)
                                <div class="text-sm text-gray-500">{{ $client->phone }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($client->lifecycle_stage === 'lead') bg-green-100 text-green-800
                                    @elseif($client->lifecycle_stage === 'prospect') bg-yellow-100 text-yellow-800
                                    @elseif($client->lifecycle_stage === 'customer') bg-purple-100 text-purple-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ __('clients.' . $client->lifecycle_stage) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $client->quotes->count() }} {{ __('clients.quotes_count') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $client->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('clients.show', $client) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        {{ __('clients.view') }}
                                    </a>
                                    <a href="{{ route('quotes.create', ['client_id' => $client->id]) }}" 
                                       class="text-green-600 hover:text-green-900">
                                        {{ __('clients.create_quote') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $clients->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('clients.no_clients') }}</h3>
                <p class="text-gray-500 mb-6">{{ __('clients.no_clients_description') }}</p>
                <a href="{{ route('clients.create') }}" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    {{ __('clients.create_first_client') }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
