{{-- Admin Header Component --}}
<header class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            {{-- Logo --}}
            <div class="flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center">
                    <img class="h-8 w-auto" src="{{ asset('images/logo.svg') }}" alt="ZenaManage">
                    <span class="ml-2 text-xl font-bold text-gray-900">ZenaManage Admin Panel</span>
                </a>
            </div>

            {{-- Navigation --}}
            <nav class="hidden md:flex space-x-8">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                    Dashboard
                </a>
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                    Users
                </a>
                <a href="{{ route('admin.tenants.index') }}" class="text-gray-500 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                    Tenants
                </a>
                <a href="{{ route('admin.settings') }}" class="text-gray-500 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                    Settings
                </a>
            </nav>

            {{-- User Menu --}}
            <div class="flex items-center space-x-4">
                {{-- Notifications --}}
                <div class="relative">
                    <button class="p-2 text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5-5-5h5v-5a7.5 7.5 0 1 0-15 0v5z" />
                        </svg>
                    </button>
                </div>

                {{-- User Dropdown --}}
                <div class="relative">
                    <button class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <img class="h-8 w-8 rounded-full" src="{{ auth()->user()->avatar ?? asset('images/default-avatar.png') }}" alt="{{ auth()->user()->name }}">
                        <span class="ml-2 text-gray-700">{{ auth()->user()->name }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
