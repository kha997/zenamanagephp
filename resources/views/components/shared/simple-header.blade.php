{{-- Simple Header Component --}}
{{-- Always renders a user menu with data-testid --}}

@props([
    'user' => null,
    'variant' => 'app'
])

@php
    $user = $user ?? Auth::user();
    $isAdmin = $variant === 'admin';
@endphp

<div class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 {{ $isAdmin ? 'bg-gradient-to-br from-red-600 to-orange-600' : 'bg-blue-600' }} rounded-lg flex items-center justify-center">
                    @if($isAdmin)
                        <span class="text-white font-bold text-lg">Z</span>
                    @else
                        <i class="fas fa-cube text-white text-sm"></i>
                    @endif
                </div>
                <span class="text-xl font-bold text-gray-900">ZenaManage</span>
            </div>
            
            <!-- User Menu -->
            @if($user)
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        Welcome, {{ $user->name }}
                    </div>
                    <div class="relative" data-testid="user-menu">
                        <button class="flex items-center space-x-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                            <div class="w-8 h-8 {{ $isAdmin ? 'bg-red-600' : 'bg-blue-600' }} rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">
                                    {{ $user->first_name ? strtoupper(substr($user->first_name, 0, 1)) : 'U' }}
                                </span>
                            </div>
                            <span class="hidden md:block text-sm font-medium text-gray-900">
                                {{ $user->first_name ? $user->first_name . ' ' . $user->last_name : $user->name }}
                            </span>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                    </div>
                </div>
            @else
                <div class="text-sm text-gray-600">
                    Not logged in
                </div>
            @endif
        </div>
    </div>
</div>
