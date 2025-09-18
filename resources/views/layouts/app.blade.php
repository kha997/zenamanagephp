<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ZenaManage')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; }
        
        .app-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 1000; }
        .sidebar.collapsed { transform: translateX(-100%); }
        
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.8rem; font-weight: bold; color: #3498db; display: flex; align-items: center; gap: 0.5rem; }
        .logo-icon { font-size: 2rem; }
        
        .sidebar-nav { padding: 1rem 0; }
        .nav-item { margin: 0.25rem 0; }
        .nav-link { display: flex; align-items: center; padding: 1rem 1.5rem; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s ease; border-left: 3px solid transparent; }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; border-left-color: #3498db; }
        .nav-link.active { background: rgba(52, 152, 219, 0.2); color: #3498db; border-left-color: #3498db; }
        .nav-icon { font-size: 1.2rem; margin-right: 1rem; width: 20px; text-align: center; }
        .nav-text { font-weight: 500; }
        
        .nav-badge { background: #e74c3c; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 10px; margin-left: auto; }
        
        /* Main Content */
        .main-content { flex: 1; margin-left: 280px; transition: margin-left 0.3s ease; }
        .main-content.expanded { margin-left: 0; }
        
        .top-bar { background: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .top-bar-left { display: flex; align-items: center; gap: 1rem; }
        .menu-toggle { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }
        .page-title { font-size: 1.5rem; font-weight: 600; color: #333; }
        
        .top-bar-right { display: flex; align-items: center; gap: 1rem; }
        .search-box { position: relative; }
        .search-input { padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1px solid #ddd; border-radius: 25px; width: 300px; font-size: 0.9rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #666; }
        
        .notification-btn { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #666; position: relative; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; font-size: 0.7rem; padding: 0.2rem 0.4rem; border-radius: 50%; }
        
        .theme-toggle { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #666; }
        
        .user-menu { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; transition: background 0.2s; }
        .user-menu:hover { background: #f8f9fa; }
        .user-avatar { width: 40px; height: 40px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .user-name { font-weight: 500; color: #333; }
        
        .content-area { padding: 2rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .search-input { width: 200px; }
        }
        
        /* Dark Mode */
        .dark-mode { background: #1a1a1a; color: #e0e0e0; }
        .dark-mode .top-bar { background: #2d2d2d; border-bottom: 1px solid #444; }
        .dark-mode .page-title { color: #e0e0e0; }
        .dark-mode .search-input { background: #3d3d3d; border-color: #555; color: #e0e0e0; }
        .dark-mode .user-menu:hover { background: #3d3d3d; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="logo-icon">🚀</span>
                    <span class="logo-text">ZenaManage</span>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="{{ route('projects.index') }}" class="nav-link {{ request()->is('projects*') ? 'active' : '' }}">
                        <span class="nav-icon">📋</span>
                        <span class="nav-text">Projects</span>
                        <span class="nav-badge">12</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="{{ route('tasks.index') }}" class="nav-link {{ request()->is('tasks*') ? 'active' : '' }}">
                        <span class="nav-icon">📝</span>
                        <span class="nav-text">Tasks</span>
                        <span class="nav-badge">48</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="{{ route('team.index') }}" class="nav-link {{ request()->is('team*') ? 'active' : '' }}">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Team</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="{{ route('documents.index') }}" class="nav-link {{ request()->is('documents*') ? 'active' : '' }}">
                        <span class="nav-icon">📄</span>
                        <span class="nav-text">Documents</span>
                        <span class="nav-badge">156</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->is('admin*') ? 'active' : '' }}">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Admin</span>
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                    <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                </div>
                
                <div class="top-bar-right">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" class="search-input" placeholder="Search projects, tasks, users..." id="globalSearch">
                    </div>
                    
                    <button class="notification-btn" onclick="showNotifications()">
                        🔔
                        <span class="notification-badge">3</span>
                    </button>
                    
                    <button class="theme-toggle" onclick="toggleDarkMode()">🌙</button>
                    
                    <div class="user-menu" onclick="showUserMenu()">
                        <div class="user-avatar">SA</div>
                        <span class="user-name">Super Admin</span>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
                @yield('content')
            </div>
        </div>
    </div>
    
    <script>
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
        
        // Dark Mode Toggle
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const themeToggle = document.querySelector('.theme-toggle');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        }
        
        // Global Search
        document.getElementById('globalSearch').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            if (query.length > 2) {
                // Implement search functionality
                console.log('Searching for:', query);
            }
        });
        
        // Notifications
        function showNotifications() {
            alert('Notifications:\n• Project Alpha completed\n• New task assigned\n• Document approved');
        }
        
        // User Menu
        function showUserMenu() {
            const menu = document.createElement('div');
            menu.style.cssText = 'position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; min-width: 200px;';
            menu.innerHTML = `
                <a href="/profile" style="display: block; padding: 1rem; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">👤 Profile Settings</a>
                <a href="#" style="display: block; padding: 1rem; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">⚙️ Account Settings</a>
                <a href="#" style="display: block; padding: 1rem; text-decoration: none; color: #333;">🚪 Logout</a>
            `;
            
            // Remove existing menu if any
            const existingMenu = document.querySelector('.user-dropdown-menu');
            if (existingMenu) existingMenu.remove();
            
            menu.className = 'user-dropdown-menu';
            document.querySelector('.user-menu').appendChild(menu);
            
            // Close menu when clicking outside
            setTimeout(() => {
                document.addEventListener('click', function closeMenu(e) {
                    if (!e.target.closest('.user-menu')) {
                        menu.remove();
                        document.removeEventListener('click', closeMenu);
                    }
                });
            }, 100);
        }
        
        // Auto-collapse sidebar on mobile
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.add('collapsed');
            document.getElementById('mainContent').classList.add('expanded');
        }
    </script>
</body>
</html>