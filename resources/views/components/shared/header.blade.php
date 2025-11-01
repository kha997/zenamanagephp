{{-- Simple Header for Dashboard --}}
{{-- No React dependency, pure Blade implementation --}}

<header class="bg-white border-b border-gray-200">
    {{-- Main Header Row --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Logo --}}
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cube text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-bold text-gray-900">ZenaManage</span>
                </div>
            </div>

            {{-- Right Side: User Menu & Notifications --}}
            <div class="flex items-center space-x-4">
                {{-- Notifications Dropdown --}}
                <div class="relative" x-data="{ showNotifications: false }">
                    <button 
                        type="button"
                        class="relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        aria-label="Notifications"
                        @click="showNotifications = !showNotifications"
                    >
                        <i class="fas fa-bell text-lg"></i>
                        @if(isset($unreadCount) && $unreadCount > 0)
                            <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-xs font-bold text-white bg-red-500 rounded-full">
                                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                            </span>
                        @endif
                    </button>

                    {{-- Notifications Dropdown Panel --}}
                    <div 
                        x-show="showNotifications"
                        @click.away="showNotifications = false"
                        x-cloak
                        class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            @forelse(isset($notifications) ? $notifications : [] as $notification)
                                <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-bell text-blue-600 text-xs"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900">{{ $notification['message'] ?? 'No message' }}</p>
                                            <p class="text-xs text-gray-500">{{ isset($notification['created_at']) ? \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() : '' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-center">
                                    <i class="fas fa-bell text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-sm text-gray-500">No notifications</p>
                                </div>
                            @endforelse
                        </div>
                        @if(isset($notifications) && count($notifications) > 0)
                            <div class="px-4 py-2 border-t border-gray-200">
                                <a href="/app/notifications" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View all notifications</a>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- User Menu --}}
                <div class="flex items-center space-x-3" data-testid="user-menu">
                    <div class="flex items-center space-x-2">
                        <div class="flex flex-col text-right">
                            <span class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</span>
                            <span class="text-xs text-gray-500">{{ Auth::user()->email }}</span>
                        </div>
                        <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center">
                            <span class="text-white text-sm font-medium">{{ substr(Auth::user()->name, 0, 1) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

