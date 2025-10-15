<div class="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-building text-white text-sm"></i>
            </div>
<div>
                <h1 class="text-lg font-bold text-gray-900">ZENA</h1>
                <p class="text-xs text-gray-500">Project Management</p>
            </div>
        </div>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        @if(isset($sidebarConfig['items']) && !empty($sidebarConfig['items']))
            @foreach($sidebarConfig['items'] as $item)
                @if($item['type'] === 'group')
                    <!-- Group Item -->
                    <div class="{{ $getItemClasses($item) }}">
                        <div class="sidebar-group-header group" onclick="toggleGroup('{{ $item['id'] }}')">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    @if(isset($item['icon']))
                                        <i class="{{ $getIconClass($item) }} mr-3 text-gray-400"></i>
                                    @endif
                                    <span class="font-medium text-gray-700">{{ $item['label'] }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button onclick="event.stopPropagation(); togglePin('{{ $item['id'] }}')" 
                                            class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-gray-200"
                                            title="Pin/Unpin">
                                        <i class="fas fa-thumbtack text-xs text-gray-400"></i>
                                    </button>
                                    <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200" id="chevron-{{ $item['id'] }}"></i>
                                </div>
                            </div>
                        </div>
                        
                        @if($hasChildren($item))
                            <div class="sidebar-group-children" id="group-{{ $item['id'] }}" style="display: none;">
                                @foreach($item['children'] as $child)
                                    <a href="{{ $buildUrl($child) }}" 
                                       class="sidebar-child-item {{ $isUrlActive($buildUrl($child)) ? 'active' : '' }}">
                                        @if(isset($child['icon']))
                                            <i class="{{ $getIconClass($child) }} mr-3 text-gray-400"></i>
                                        @endif
                                        <span>{{ $child['label'] }}</span>
                                        @if($child['type'] === 'external')
                                            <i class="fas fa-external-link-alt ml-auto text-xs text-gray-400"></i>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    
                @elseif($item['type'] === 'link')
                    <!-- Link Item -->
                    <div class="sidebar-item-wrapper group">
                        <a href="{{ $buildUrl($item) }}" 
                           class="{{ $getItemClasses($item) }} {{ $isUrlActive($buildUrl($item)) ? 'active' : '' }}">
                            @if(isset($item['icon']))
                                <i class="{{ $getIconClass($item) }} mr-3 text-gray-400"></i>
                            @endif
                            <span>{{ $item['label'] }}</span>
                            @if($shouldShowBadge($item))
                                <span class="sidebar-badge" id="badge-{{ $item['id'] }}">{{ $getBadgeCount($item) }}</span>
                            @endif
                        </a>
                        <button onclick="togglePin('{{ $item['id'] }}')" 
                                class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-gray-200 absolute right-2 top-1/2 transform -translate-y-1/2"
                                title="Pin/Unpin">
                            <i class="fas fa-thumbtack text-xs text-gray-400"></i>
                        </button>
                    </div>
                    
                @elseif($item['type'] === 'external')
                    <!-- External Link Item -->
                    <a href="{{ $buildUrl($item) }}" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="{{ $getItemClasses($item) }}">
                        @if(isset($item['icon']))
                            <i class="{{ $getIconClass($item) }} mr-3 text-gray-400"></i>
                        @endif
                        <span>{{ $item['label'] }}</span>
                        <i class="fas fa-external-link-alt ml-auto text-xs text-gray-400"></i>
                    </a>
                    
                @elseif($item['type'] === 'divider')
                    <!-- Divider -->
                    <hr class="sidebar-divider">
                @endif
            @endforeach
        @else
            <!-- Default sidebar when no config -->
            <div class="sidebar-item">
                <a href="/dashboard" class="{{ $isUrlActive('/dashboard') ? 'active' : '' }}">
                    <i class="fas fa-tachometer-alt mr-3 text-gray-400"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        @endif
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        @if($currentRole)
            <div class="text-xs text-gray-500 text-center">
                Role: {{ ucwords(str_replace('_', ' ', $currentRole)) }}
            </div>
        @endif
    </div>
</div>

<style>
.sidebar {
    @apply w-64 bg-white shadow-lg flex flex-col h-full;
}

.sidebar-header {
    @apply p-6 border-b;
}

.sidebar-nav {
    @apply flex-1 p-4 space-y-2 overflow-y-auto;
}

.sidebar-item {
    @apply block py-2 px-3 text-sm text-gray-600 rounded-md transition-colors duration-200;
}

.sidebar-item:hover {
    @apply bg-gray-100 text-gray-900;
}

.sidebar-item.active {
    @apply bg-blue-100 text-blue-900;
}

.sidebar-group {
    @apply mb-2;
}

.sidebar-group-header {
    @apply flex items-center justify-between py-2 px-3 text-sm font-medium text-gray-700 cursor-pointer rounded-md hover:bg-gray-100 transition-colors duration-200;
}

.sidebar-group-children {
    @apply ml-4 space-y-1;
}

.sidebar-child-item {
    @apply flex items-center py-2 px-3 text-sm text-gray-600 rounded-md hover:bg-gray-100 transition-colors duration-200;
}

.sidebar-child-item.active {
    @apply bg-blue-100 text-blue-900;
}

.sidebar-divider {
    @apply my-2 border-gray-200;
}

.sidebar-badge {
    @apply ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1 min-w-[20px] text-center;
}

.sidebar-item-wrapper {
    @apply relative;
}

.sidebar-footer {
    @apply p-4 border-t;
}

.sidebar-pinned {
    @apply border-l-4 border-blue-500;
}
</style>

<script>
function toggleGroup(groupId) {
    const group = document.getElementById('group-' + groupId);
    const chevron = document.getElementById('chevron-' + groupId);
    
    if (group.style.display === 'none') {
        group.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
    } else {
        group.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}

// Load badges for items that have show_badge_from
document.addEventListener('DOMContentLoaded', function() {
    const badgeItems = document.querySelectorAll('[id^="badge-"]');
    
    badgeItems.forEach(function(badgeElement) {
        const itemId = badgeElement.id.replace('badge-', '');
        loadBadge(itemId);
    });
});

function loadBadge(itemId) {
    fetch(`/api/badges/${itemId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badgeElement = document.getElementById('badge-' + itemId);
            if (badgeElement) {
                badgeElement.textContent = data.data.count;
                if (data.data.count > 0) {
                    badgeElement.style.display = 'inline-block';
                } else {
                    badgeElement.style.display = 'none';
                }
            }
        }
    })
    .catch(error => {
        console.error('Error loading badge for item:', itemId, error);
    });
}

// Pin/Unpin functionality
function togglePin(itemId) {
    fetch('/api/user-preferences/pin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            item_id: itemId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Toggle visual state
            const pinButton = event.target.closest('button');
            const icon = pinButton.querySelector('i');
            
            if (icon.classList.contains('fa-thumbtack')) {
                icon.classList.remove('fa-thumbtack');
                icon.classList.add('fa-thumbtack', 'text-blue-500');
                pinButton.title = 'Unpin';
            } else {
                icon.classList.remove('text-blue-500');
                pinButton.title = 'Pin';
            }
            
            // Reload sidebar to reflect changes
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while pinning/unpinning item');
    });
}

// Hide/Show functionality
function toggleHide(itemId) {
    fetch('/api/user-preferences/hide', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            item_id: itemId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide the item visually
            const itemElement = document.querySelector(`[data-item-id="${itemId}"]`);
            if (itemElement) {
                itemElement.style.display = 'none';
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while hiding item');
    });
}
</script>