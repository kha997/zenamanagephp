@extends('layouts.app')

@section('title', __('clients.create_client'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('clients.create_client') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('clients.create_client_description') }}</p>
            </div>
            <div>
                <a href="{{ route('clients.index') }}" 
                   class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>
                    {{ __('clients.back_to_clients') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Create Client Form -->
    <div class="bg-white rounded-lg shadow">
        <form action="{{ route('clients.store') }}" method="POST" class="p-6">
            @csrf

            <!-- Basic Information -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('clients.basic_information') }}</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="{{ old('name') }}"
                               required
                               class="form-input w-full @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.email') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="{{ old('email') }}"
                               required
                               class="form-input w-full @error('email') border-red-500 @enderror">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.phone') }}
                        </label>
                        <input type="text" 
                               id="phone" 
                               name="phone" 
                               value="{{ old('phone') }}"
                               class="form-input w-full @error('phone') border-red-500 @enderror">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.company') }}
                        </label>
                        <input type="text" 
                               id="company" 
                               name="company" 
                               value="{{ old('company') }}"
                               class="form-input w-full @error('company') border-red-500 @enderror">
                        @error('company')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="lifecycle_stage" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.lifecycle_stage') }}
                        </label>
                        <select id="lifecycle_stage" 
                                name="lifecycle_stage" 
                                class="form-select w-full @error('lifecycle_stage') border-red-500 @enderror">
                            <option value="lead" {{ old('lifecycle_stage', 'lead') === 'lead' ? 'selected' : '' }}>
                                {{ __('clients.lead') }}
                            </option>
                            <option value="prospect" {{ old('lifecycle_stage') === 'prospect' ? 'selected' : '' }}>
                                {{ __('clients.prospect') }}
                            </option>
                            <option value="customer" {{ old('lifecycle_stage') === 'customer' ? 'selected' : '' }}>
                                {{ __('clients.customer') }}
                            </option>
                            <option value="inactive" {{ old('lifecycle_stage') === 'inactive' ? 'selected' : '' }}>
                                {{ __('clients.inactive') }}
                            </option>
                        </select>
                        @error('lifecycle_stage')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('clients.address_information') }}</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="address_street" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.street') }}
                        </label>
                        <input type="text" 
                               id="address_street" 
                               name="address[street]" 
                               value="{{ old('address.street') }}"
                               class="form-input w-full @error('address.street') border-red-500 @enderror">
                        @error('address.street')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address_city" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.city') }}
                        </label>
                        <input type="text" 
                               id="address_city" 
                               name="address[city]" 
                               value="{{ old('address.city') }}"
                               class="form-input w-full @error('address.city') border-red-500 @enderror">
                        @error('address.city')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address_state" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.state') }}
                        </label>
                        <input type="text" 
                               id="address_state" 
                               name="address[state]" 
                               value="{{ old('address.state') }}"
                               class="form-input w-full @error('address.state') border-red-500 @enderror">
                        @error('address.state')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address_postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.postal_code') }}
                        </label>
                        <input type="text" 
                               id="address_postal_code" 
                               name="address[postal_code]" 
                               value="{{ old('address.postal_code') }}"
                               class="form-input w-full @error('address.postal_code') border-red-500 @enderror">
                        @error('address.postal_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address_country" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('clients.country') }}
                        </label>
                        <input type="text" 
                               id="address_country" 
                               name="address[country]" 
                               value="{{ old('address.country') }}"
                               class="form-input w-full @error('address.country') border-red-500 @enderror">
                        @error('address.country')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('clients.additional_information') }}</h3>
                
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('clients.notes') }}
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="4"
                              class="form-textarea w-full @error('notes') border-red-500 @enderror"
                              placeholder="{{ __('clients.notes_placeholder') }}">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                <a href="{{ route('clients.index') }}" 
                   class="btn btn-secondary">
                    {{ __('clients.cancel') }}
                </a>
                <button type="submit" 
                        class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i>
                    {{ __('clients.create_client') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
