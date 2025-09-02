<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Z.E.N.A Project Management')</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Styles -->
    @vite(['resources/css/app.css'])
    
    @stack('styles')
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="{{ route('dashboard') }}" class="logo">
                    <i class="fas fa-project-diagram"></i>
                    Z.E.N.A
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="fas fa-tachometer-alt nav-icon"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('projects.index') }}" class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                            <i class="fas fa-folder-open nav-icon"></i>
                            Dự án
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('tasks.index') }}" class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
                            <i class="fas fa-tasks nav-icon"></i>
                            Công việc
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}">
                            <i class="fas fa-file-alt nav-icon"></i>
                            Tài liệu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('change-requests.index') }}" class="nav-link {{ request()->routeIs('change-requests.*') ? 'active' : '' }}">
                            <i class="fas fa-exchange-alt nav-icon"></i>
                            Yêu cầu thay đổi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="fas fa-chart-bar nav-icon"></i>
                            Báo cáo
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                            <i class="fas fa-bell nav-icon"></i>
                            Thông báo
                            @if(Auth::user()->unreadNotifications->count() > 0)
                                <span class="badge badge-danger">{{ Auth::user()->unreadNotifications->count() }}</span>
                            @endif
                        </a>
                    </li>
                    
                    @can('manage_users')
                    <li class="nav-item">
                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="fas fa-users nav-icon"></i>
                            Người dùng
                        </a>
                    </li>
                    @endcan
                    
                    @can('manage_roles')
                    <li class="nav-item">
                        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <i class="fas fa-user-shield nav-icon"></i>
                            Phân quyền
                        </a>
                    </li>
                    @endcan
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="btn btn-secondary" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                </div>
                
                <div class="header-right d-flex align-center gap-3">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-secondary" id="notifications-toggle">
                            <i class="fas fa-bell"></i>
                            @if(Auth::user()->unreadNotifications->count() > 0)
                                <span class="badge badge-danger">{{ Auth::user()->unreadNotifications->count() }}</span>
                            @endif
                        </button>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn btn-secondary" id="user-menu-toggle">
                            <i class="fas fa-user"></i>
                            {{ Auth::user()->name }}
                        </button>
                        <div class="dropdown-menu" id="user-menu">
                            <a href="{{ route('profile.edit') }}" class="dropdown-item">
                                <i class="fas fa-user-edit"></i>
                                Hồ sơ
                            </a>
                            <a href="{{ route('settings.index') }}" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Cài đặt
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item" id="logout-btn">
                                <i class="fas fa-sign-out-alt"></i>
                                Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        {{ session('success') }}
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        {{ session('error') }}
                    </div>
                @endif
                
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @yield('content')
            </div>
        </main>
    </div>
    
    <!-- Scripts -->
    @vite(['resources/js/app.js'])
    
    @stack('scripts')
</body>
</html>