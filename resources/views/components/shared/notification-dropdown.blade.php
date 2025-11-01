@props(['notifications' => [], 'unreadCount' => 0])

<div class="relative" x-data="notificationDropdown()">
    <!-- Notification Bell -->
    <button @click="toggleDropdown()" 
            class="relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-lg">
        <i class="fas fa-bell text-lg"></i>
        
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown Menu -->
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.away="closeDropdown()"
         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-notification"
         style="display: none;">
        
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('notifications.notifications') }}</h3>
                @if($unreadCount > 0)
                    <button @click="markAllAsRead()" 
                            class="text-sm text-blue-600 hover:text-blue-800">
                        {{ __('notifications.mark_all_read') }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
                <div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                     @click="markAsRead({{ $notification->id }})"
                     :class="{ 'bg-blue-50': !{{ $notification->read_at ? 'true' : 'false' }} }">
                    
                    <div class="flex items-start space-x-3">
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            @switch($notification->type)
                                @case('task_completed')
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    @break
                                @case('quote_sent')
                                    <i class="fas fa-file-invoice text-blue-500"></i>
                                    @break
                                @case('client_created')
                                    <i class="fas fa-user-plus text-purple-500"></i>
                                    @break
                                @default
                                    <i class="fas fa-info-circle text-gray-500"></i>
                            @endswitch
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $notification->title }}
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                {{ $notification->message }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <!-- Unread Indicator -->
                        @if(!$notification->read_at)
                            <div class="flex-shrink-0">
                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-3xl mb-2"></i>
                    <p>{{ __('notifications.no_notifications') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-200">
            {{-- TODO: Implement notifications route --}}
            {{-- <a href="{{ route('app.notifications.index') }}" 
               class="block text-center text-sm text-blue-600 hover:text-blue-800">
                {{ __('notifications.view_all') }}
            </a> --}}
            <div class="block text-center text-sm text-gray-500">
                {{ __('notifications.view_all') }}
            </div>
        </div>
    </div>
</div>

<script>
function notificationDropdown() {
    return {
        isOpen: false,
        
        toggleDropdown() {
            // Close other dropdowns
            this.closeOtherDropdowns();
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.loadNotifications();
            }
        },
        
        closeOtherDropdowns() {
            // Close user menu dropdown
            const userMenuComponent = document.querySelector('[x-data*="sharedHeaderComponent"]');
            if (userMenuComponent && userMenuComponent._x_dataStack) {
                const headerData = userMenuComponent._x_dataStack[0];
                if (headerData && headerData.userMenuOpen) {
                    headerData.userMenuOpen = false;
                }
            }
            
            // Close focus mode dropdown
            const focusModeComponent = document.querySelector('[data-focus-mode-toggle]');
            if (focusModeComponent && focusModeComponent._x_dataStack) {
                const focusData = focusModeComponent._x_dataStack[0];
                if (focusData && focusData.isActive) {
                    focusData.isActive = false;
                }
            }
        },
        
        closeDropdown() {
            this.isOpen = false;
        },
        
        async loadNotifications() {
            try {
                const response = await fetch('/api/v1/app/notifications?limit=10');
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Update notifications in the dropdown
                    this.notifications = data.data.data;
                }
            } catch (error) {
                console.error('Failed to load notifications:', error);
            }
        },
        
        async markAsRead(notificationId) {
            try {
                const response = await fetch(`/api/v1/app/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (response.ok) {
                    // Update notification as read
                    const notification = this.notifications.find(n => n.id === notificationId);
                    if (notification) {
                        notification.read_at = new Date().toISOString();
                    }
                    
                    // Update unread count
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                }
            } catch (error) {
                console.error('Failed to mark notification as read:', error);
            }
        },
        
        async markAllAsRead() {
            try {
                const response = await fetch('/api/v1/app/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (response.ok) {
                    // Mark all notifications as read
                    this.notifications.forEach(notification => {
                        notification.read_at = new Date().toISOString();
                    });
                    
                    // Reset unread count
                    this.unreadCount = 0;
                }
            } catch (error) {
                console.error('Failed to mark all notifications as read:', error);
            }
        }
    }
}
</script>
