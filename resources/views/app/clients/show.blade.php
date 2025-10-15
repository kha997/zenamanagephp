@extends('layouts.app-layout')

@section('title', $client->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $client->name }}</h1>
                @if($client->company)
                <p class="mt-2 text-gray-600">{{ $client->company }}</p>
                @endif
                <div class="mt-2 flex items-center space-x-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($client->lifecycle_stage === 'lead') bg-green-100 text-green-800
                        @elseif($client->lifecycle_stage === 'prospect') bg-yellow-100 text-yellow-800
                        @elseif($client->lifecycle_stage === 'customer') bg-purple-100 text-purple-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ __('clients.' . $client->lifecycle_stage) }}
                    </span>
                    <span class="text-sm text-gray-500">
                        {{ __('clients.created_on') }} {{ $client->created_at->format('M d, Y') }}
                    </span>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('quotes.create', ['client_id' => $client->id]) }}" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    {{ __('clients.create_quote') }}
                </a>
                <a href="{{ route('clients.edit', $client) }}" 
                   class="btn btn-secondary">
                    <i class="fas fa-edit mr-2"></i>
                    {{ __('clients.edit') }}
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Client Information -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('clients.client_information') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.name') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $client->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.email') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $client->email }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $client->email }}
                                </a>
                            </dd>
                        </div>
                        @if($client->phone)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.phone') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:{{ $client->phone }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $client->phone }}
                                </a>
                            </dd>
                        </div>
                        @endif
                        @if($client->company)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.company') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $client->company }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.lifecycle_stage') }}</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($client->lifecycle_stage === 'lead') bg-green-100 text-green-800
                                    @elseif($client->lifecycle_stage === 'prospect') bg-yellow-100 text-yellow-800
                                    @elseif($client->lifecycle_stage === 'customer') bg-purple-100 text-purple-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ __('clients.' . $client->lifecycle_stage) }}
                                </span>
                            </dd>
                        </div>
                        @if($client->formatted_address)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.address') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $client->formatted_address }}</dd>
                        </div>
                        @endif
                        @if($client->notes)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">{{ __('clients.notes') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $client->notes }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Quotes History -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('clients.quotes_history') }}</h3>
                        <a href="{{ route('quotes.create', ['client_id' => $client->id]) }}" 
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-plus mr-1"></i>
                            {{ __('clients.new_quote') }}
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    @if($client->quotes->count() > 0)
                        <div class="space-y-4">
                            @foreach($client->quotes as $quote)
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <a href="{{ route('quotes.show', $quote) }}" class="hover:text-indigo-600">
                                                    {{ $quote->title }}
                                                </a>
                                            </h4>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                @if($quote->status === 'draft') bg-gray-100 text-gray-800
                                                @elseif($quote->status === 'sent') bg-blue-100 text-blue-800
                                                @elseif($quote->status === 'viewed') bg-yellow-100 text-yellow-800
                                                @elseif($quote->status === 'accepted') bg-green-100 text-green-800
                                                @elseif($quote->status === 'rejected') bg-red-100 text-red-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ __('quotes.' . $quote->status) }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                @if($quote->type === 'design') bg-purple-100 text-purple-800
                                                @else bg-orange-100 text-orange-800
                                                @endif">
                                                {{ __('quotes.' . $quote->type) }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                            <span>{{ __('quotes.amount') }}: ${{ number_format($quote->final_amount, 2) }}</span>
                                            <span>{{ __('quotes.valid_until') }}: {{ $quote->valid_until->format('M d, Y') }}</span>
                                            <span>{{ __('quotes.created') }}: {{ $quote->created_at->format('M d, Y') }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('quotes.show', $quote) }}" 
                                           class="text-indigo-600 hover:text-indigo-900 text-sm">
                                            {{ __('clients.view') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-file-invoice text-gray-400 text-3xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('clients.no_quotes') }}</h3>
                            <p class="text-gray-500 mb-4">{{ __('clients.no_quotes_description') }}</p>
                            <a href="{{ route('quotes.create', ['client_id' => $client->id]) }}" 
                               class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i>
                                {{ __('clients.create_first_quote') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Projects -->
            @if($client->projects->count() > 0)
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('clients.projects') }}</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @foreach($client->projects as $project)
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900">
                                        <a href="{{ route('projects.show', $project) }}" class="hover:text-indigo-600">
                                            {{ $project->name }}
                                        </a>
                                    </h4>
                                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                        <span>{{ __('projects.status') }}: {{ __('projects.' . $project->status) }}</span>
                                        <span>{{ __('projects.budget') }}: ${{ number_format($project->budget, 2) }}</span>
                                        <span>{{ __('projects.created') }}: {{ $project->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('projects.show', $project) }}" 
                                       class="text-indigo-600 hover:text-indigo-900 text-sm">
                                        {{ __('clients.view') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-8">
            <!-- Quote Statistics -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('clients.quote_statistics') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.total_quotes') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $quoteStats['total'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.draft') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $quoteStats['draft'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.sent') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $quoteStats['sent'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.viewed') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $quoteStats['viewed'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.accepted') }}</dt>
                            <dd class="text-sm font-medium text-green-600">{{ $quoteStats['accepted'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.rejected') }}</dt>
                            <dd class="text-sm font-medium text-red-600">{{ $quoteStats['rejected'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('clients.expired') }}</dt>
                            <dd class="text-sm font-medium text-gray-600">{{ $quoteStats['expired'] }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('clients.recent_activity') }}</h3>
                </div>
                <div class="p-6">
                    @if($recentActivity->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentActivity as $activity)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    @if($activity instanceof \App\Models\Quote)
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-file-invoice text-blue-600 text-sm"></i>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-project-diagram text-green-600 text-sm"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        @if($activity instanceof \App\Models\Quote)
                                            {{ __('clients.quote_created') }}: {{ $activity->title }}
                                        @else
                                            {{ __('clients.project_created') }}: {{ $activity->name }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-history text-gray-400 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-500">{{ __('clients.no_recent_activity') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Lifecycle Management -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('clients.lifecycle_management') }}</h3>
                </div>
                <div class="p-6">
                    <form action="{{ route('clients.updateLifecycleStage', $client) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        
                        <div>
                            <label for="lifecycle_stage" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('clients.update_lifecycle_stage') }}
                            </label>
                            <select id="lifecycle_stage" 
                                    name="lifecycle_stage" 
                                    class="form-select w-full">
                                <option value="lead" {{ $client->lifecycle_stage === 'lead' ? 'selected' : '' }}>
                                    {{ __('clients.lead') }}
                                </option>
                                <option value="prospect" {{ $client->lifecycle_stage === 'prospect' ? 'selected' : '' }}>
                                    {{ __('clients.prospect') }}
                                </option>
                                <option value="customer" {{ $client->lifecycle_stage === 'customer' ? 'selected' : '' }}>
                                    {{ __('clients.customer') }}
                                </option>
                                <option value="inactive" {{ $client->lifecycle_stage === 'inactive' ? 'selected' : '' }}>
                                    {{ __('clients.inactive') }}
                                </option>
                            </select>
                        </div>
                        
                        <button type="submit" 
                                class="btn btn-primary w-full">
                            <i class="fas fa-save mr-2"></i>
                            {{ __('clients.update_stage') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
