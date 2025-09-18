{{-- Main Navigation Component --}}
@props(['currentRoute' => ''])

<nav class="zena-main-nav" role="navigation" aria-label="Main navigation">
    <div class="zena-nav-container">
        {{-- Logo/Brand --}}
        <div class="zena-nav-brand">
            <a href="/dashboard" class="zena-nav-brand-link">
                <div class="zena-nav-logo">
                    <i class="fas fa-cube text-blue-600"></i>
                </div>
                <span class="zena-nav-brand-text">ZenaManage</span>
            </a>
        </div>

        {{-- Desktop Navigation --}}
        <div class="zena-nav-desktop">
            <ul class="zena-nav-list">
                <li class="zena-nav-item-wrapper">
                    <a href="/dashboard" 
                       class="zena-nav-item {{ $currentRoute === 'dashboard' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'dashboard' ? 'page' : 'false' }}">
                        <i class="fas fa-home zena-nav-icon"></i>
                        <span class="zena-nav-label">Dashboard</span>
                        @if($currentRoute === 'dashboard')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-item-wrapper">
                    <a href="/tasks" 
                       class="zena-nav-item {{ $currentRoute === 'tasks' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'tasks' ? 'page' : 'false' }}">
                        <i class="fas fa-tasks zena-nav-icon"></i>
                        <span class="zena-nav-label">Tasks</span>
                        @if($currentRoute === 'tasks')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-item-wrapper">
                    <a href="/projects" 
                       class="zena-nav-item {{ $currentRoute === 'projects' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'projects' ? 'page' : 'false' }}">
                        <i class="fas fa-project-diagram zena-nav-icon"></i>
                        <span class="zena-nav-label">Projects</span>
                        @if($currentRoute === 'projects')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-item-wrapper">
                    <a href="/documents" 
                       class="zena-nav-item {{ $currentRoute === 'documents' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'documents' ? 'page' : 'false' }}">
                        <i class="fas fa-file-alt zena-nav-icon"></i>
                        <span class="zena-nav-label">Documents</span>
                        @if($currentRoute === 'documents')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-item-wrapper">
                    <a href="/team" 
                       class="zena-nav-item {{ $currentRoute === 'team' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'team' ? 'page' : 'false' }}">
                        <i class="fas fa-users zena-nav-icon"></i>
                        <span class="zena-nav-label">Team</span>
                        @if($currentRoute === 'team')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-item-wrapper">
                    <a href="/templates" 
                       class="zena-nav-item {{ $currentRoute === 'templates' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'templates' ? 'page' : 'false' }}">
                        <i class="fas fa-magic zena-nav-icon"></i>
                        <span class="zena-nav-label">Templates</span>
                        @if($currentRoute === 'templates')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-item-wrapper">
                    <a href="/admin" 
                       class="zena-nav-item {{ $currentRoute === 'admin' ? 'zena-nav-item-active' : '' }}"
                       aria-current="{{ $currentRoute === 'admin' ? 'page' : 'false' }}">
                        <i class="fas fa-cog zena-nav-icon"></i>
                        <span class="zena-nav-label">Admin</span>
                        @if($currentRoute === 'admin')
                            <div class="zena-nav-indicator"></div>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        {{-- User Menu --}}
        <div class="zena-nav-user">
            <div class="zena-nav-user-menu" x-data="{ open: false }">
                <button @click="open = !open" 
                        class="zena-nav-user-button"
                        aria-expanded="false"
                        aria-haspopup="true">
                    <div class="zena-nav-user-avatar">
                        @yield('user-initials', 'U')
                    </div>
                    <span class="zena-nav-user-name">@yield('user-name', 'User')</span>
                    <i class="fas fa-chevron-down zena-nav-user-chevron" :class="{ 'rotate-180': open }"></i>
                </button>

                {{-- Dropdown Menu --}}
                <div x-show="open" 
                     @click.away="open = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="zena-nav-user-dropdown">
                    <div class="zena-nav-user-info">
                        <div class="zena-nav-user-info-name">@yield('user-name', 'User')</div>
                        <div class="zena-nav-user-info-email">@yield('user-email', 'user@example.com')</div>
                    </div>
                    
                    <div class="zena-nav-user-divider"></div>
                    
                    <a href="/profile" class="zena-nav-user-item">
                        <i class="fas fa-user zena-nav-user-item-icon"></i>
                        <span>Profile</span>
                    </a>
                    
                    <a href="/settings" class="zena-nav-user-item">
                        <i class="fas fa-cog zena-nav-user-item-icon"></i>
                        <span>Settings</span>
                    </a>
                    
                    <a href="/help" class="zena-nav-user-item">
                        <i class="fas fa-question-circle zena-nav-user-item-icon"></i>
                        <span>Help & Support</span>
                    </a>
                    
                    <div class="zena-nav-user-divider"></div>
                    
                    <a href="/logout" class="zena-nav-user-item zena-nav-user-item-danger">
                        <i class="fas fa-sign-out-alt zena-nav-user-item-icon"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Mobile Menu Toggle --}}
        <button class="zena-nav-mobile-toggle" 
                x-data="{ open: false }"
                @click="open = !open"
                aria-expanded="false"
                aria-label="Toggle navigation menu">
            <span class="zena-nav-mobile-toggle-line"></span>
            <span class="zena-nav-mobile-toggle-line"></span>
            <span class="zena-nav-mobile-toggle-line"></span>
        </button>
    </div>

    {{-- Mobile Navigation --}}
    <div class="zena-nav-mobile" 
         x-data="{ open: false }"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2">
        <div class="zena-nav-mobile-content">
            <ul class="zena-nav-mobile-list">
                <li class="zena-nav-mobile-item">
                    <a href="/dashboard" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'dashboard' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-home zena-nav-mobile-icon"></i>
                        <span>Dashboard</span>
                        @if($currentRoute === 'dashboard')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-mobile-item">
                    <a href="/tasks" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'tasks' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-tasks zena-nav-mobile-icon"></i>
                        <span>Tasks</span>
                        @if($currentRoute === 'tasks')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-mobile-item">
                    <a href="/projects" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'projects' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-project-diagram zena-nav-mobile-icon"></i>
                        <span>Projects</span>
                        @if($currentRoute === 'projects')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-mobile-item">
                    <a href="/documents" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'documents' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-file-alt zena-nav-mobile-icon"></i>
                        <span>Documents</span>
                        @if($currentRoute === 'documents')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-mobile-item">
                    <a href="/team" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'team' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-users zena-nav-mobile-icon"></i>
                        <span>Team</span>
                        @if($currentRoute === 'team')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-mobile-item">
                    <a href="/templates" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'templates' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-magic zena-nav-mobile-icon"></i>
                        <span>Templates</span>
                        @if($currentRoute === 'templates')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>

                <li class="zena-nav-mobile-item">
                    <a href="/admin" 
                       class="zena-nav-mobile-link {{ $currentRoute === 'admin' ? 'zena-nav-mobile-link-active' : '' }}">
                        <i class="fas fa-cog zena-nav-mobile-icon"></i>
                        <span>Admin</span>
                        @if($currentRoute === 'admin')
                            <i class="fas fa-check zena-nav-mobile-check"></i>
                        @endif
                    </a>
                </li>
            </ul>

            {{-- Mobile User Section --}}
            <div class="zena-nav-mobile-user">
                <div class="zena-nav-mobile-user-info">
                    <div class="zena-nav-mobile-user-avatar">
                        @yield('user-initials', 'U')
                    </div>
                    <div>
                        <div class="zena-nav-mobile-user-name">@yield('user-name', 'User')</div>
                        <div class="zena-nav-mobile-user-email">@yield('user-email', 'user@example.com')</div>
                    </div>
                </div>
                
                <div class="zena-nav-mobile-user-actions">
                    <a href="/profile" class="zena-btn zena-btn-outline zena-btn-sm">
                        <i class="fas fa-user mr-2"></i>
                        Profile
                    </a>
                    <a href="/logout" class="zena-btn zena-btn-danger zena-btn-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>
