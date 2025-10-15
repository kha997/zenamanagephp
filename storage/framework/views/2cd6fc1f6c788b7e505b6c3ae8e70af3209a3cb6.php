<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['user' => null]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['user' => null]); ?>
<?php foreach (array_filter((['user' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-rocket"></i>
            <span>ZENA</span>
        </div>
    </div>
    
    <nav class="sidebar-nav" id="sidebar-nav">
        <!-- Sidebar items will be loaded dynamically via JavaScript -->
        <div class="sidebar-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading navigation...</span>
        </div>
    </nav>
</div>

<style>
.sidebar-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: rgba(255,255,255,0.6);
    gap: 0.5rem;
}

.sidebar-loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.nav-group {
    margin-bottom: 1rem;
}

.nav-group-title {
    padding: 0.5rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255,255,255,0.5);
    margin-bottom: 0.5rem;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.nav-item.active {
    background: rgba(255,255,255,0.15);
    color: white;
    border-left-color: white;
}

.nav-item i {
    width: 20px;
    margin-right: 0.75rem;
    text-align: center;
}

.nav-item span {
    font-weight: 500;
}

.nav-item .badge {
    position: absolute;
    right: 1rem;
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 500;
}

.nav-item .badge.badge-danger {
    background: #ef4444;
}

.nav-item .badge.badge-warning {
    background: #f59e0b;
}

.nav-item .badge.badge-success {
    background: #10b981;
}

.nav-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 1rem 0;
}

.nav-external {
    position: relative;
}

.nav-external::after {
    content: '\f35d';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 1rem;
    font-size: 0.75rem;
    opacity: 0.6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSidebarConfig();
});

function loadSidebarConfig() {
    fetch('/api/sidebar/config')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderSidebar(data.config);
            } else {
                console.error('Failed to load sidebar config:', data.message);
                renderDefaultSidebar();
            }
        })
        .catch(error => {
            console.error('Error loading sidebar config:', error);
            renderDefaultSidebar();
        });
}

function renderSidebar(config) {
    const sidebarNav = document.getElementById('sidebar-nav');
    if (!sidebarNav) return;

    sidebarNav.innerHTML = '';

    if (!config || !config.items || config.items.length === 0) {
        renderDefaultSidebar();
        return;
    }

    config.items.forEach(item => {
        if (!item.enabled) return;

        const element = createSidebarItem(item);
        if (element) {
            sidebarNav.appendChild(element);
        }
    });
}

function createSidebarItem(item) {
    const div = document.createElement('div');
    
    if (item.type === 'group') {
        div.className = 'nav-group';
        div.innerHTML = `
            <div class="nav-group-title">${item.label}</div>
        `;
        return div;
    }
    
    if (item.type === 'divider') {
        div.className = 'nav-divider';
        return div;
    }
    
    if (item.type === 'link' || item.type === 'external') {
        const isActive = checkIfActive(item);
        const badgeHtml = item.badge_count > 0 ? `<span class="badge badge-${item.badge_type || 'default'}">${item.badge_count}</span>` : '';
        
        div.innerHTML = `
            <a href="${item.type === 'external' ? item.href : item.to}" 
               class="nav-item ${isActive ? 'active' : ''} ${item.type === 'external' ? 'nav-external' : ''}"
               ${item.type === 'external' ? 'target="_blank"' : ''}>
                <i class="${item.icon || 'fas fa-circle'}"></i>
                <span>${item.label}</span>
                ${badgeHtml}
            </a>
        `;
        return div;
    }
    
    return null;
}

function checkIfActive(item) {
    if (item.type !== 'link' || !item.to) return false;
    
    const currentPath = window.location.pathname;
    const itemPath = item.to;
    
    // Exact match
    if (currentPath === itemPath) return true;
    
    // Starts with match for nested routes
    if (itemPath !== '/' && currentPath.startsWith(itemPath)) return true;
    
    return false;
}

function renderDefaultSidebar() {
    const sidebarNav = document.getElementById('sidebar-nav');
    if (!sidebarNav) return;

    sidebarNav.innerHTML = `
        <a href="/dashboard" class="nav-item ${window.location.pathname === '/dashboard' ? 'active' : ''}">
            <span>Dashboard</span>
        </a>
        <a href="/projects" class="nav-item ${window.location.pathname.startsWith('/projects') ? 'active' : ''}">
            <span>Projects</span>
        </a>
        <a href="/tasks" class="nav-item ${window.location.pathname.startsWith('/tasks') ? 'active' : ''}">
            <span>Tasks</span>
        </a>
        <a href="/team" class="nav-item ${window.location.pathname.startsWith('/team') ? 'active' : ''}">
            <span>Team</span>
        </a>
        <div class="nav-divider"></div>
        <a href="/admin/sidebar-builder" class="nav-item ${window.location.pathname.startsWith('/admin') ? 'active' : ''}">
            <span>Admin</span>
        </a>
    `;
}

// Update badges periodically
setInterval(updateBadges, 30000); // Every 30 seconds

function updateBadges() {
    fetch('/api/sidebar/badges')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateBadgeCounts(data.badges);
            }
        })
        .catch(error => {
            console.error('Error updating badges:', error);
        });
}

function updateBadgeCounts(badges) {
    Object.keys(badges).forEach(itemId => {
        const badgeElement = document.querySelector(`[data-item-id="${itemId}"] .badge`);
        if (badgeElement) {
            badgeElement.textContent = badges[itemId];
        }
    });
}
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation/dynamic-sidebar.blade.php ENDPATH**/ ?>