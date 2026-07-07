<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'ZenaManage'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/operator.css'])
    @stack('styles')
</head>
<body>
    <div class="operator-shell">
        <aside class="operator-sidebar">
            <div class="operator-brand">
                <span class="operator-brand-kicker">Operator</span>
                <a href="{{ route('operator.dashboard') }}" class="operator-brand-title">Procurement UI</a>
            </div>

            <nav class="operator-nav">
                <span class="operator-nav-section">Tổng quan</span>
                <a href="{{ route('operator.dashboard') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.dashboard') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    <span>Bảng điều hành</span>
                </a>

                <span class="operator-nav-section">Mua sắm</span>
                <a href="{{ route('operator.material-requests.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.material-requests.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    <span>Yêu cầu vật tư</span>
                </a>
                <a href="{{ route('operator.receipts.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.receipts.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    <span>Phiếu nhập</span>
                </a>

                <span class="operator-nav-section">Tài liệu</span>
                <a href="{{ route('api.zena.rfis.index', [], false) }}"
                   class="operator-nav-link {{ request()->routeIs('operator.rfis.*') ? 'is-active' : '' }}"
                   title="Xem qua API — trang web đang xây dựng">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>RFI</span>
                </a>
                <a href="{{ route('api.zena.submittals.index', [], false) }}"
                   class="operator-nav-link {{ request()->routeIs('operator.submittals.*') ? 'is-active' : '' }}"
                   title="Xem qua API — trang web đang xây dựng">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Submittals</span>
                </a>
                <a href="{{ route('api.zena.change-requests.index', [], false) }}"
                   class="operator-nav-link {{ request()->routeIs('operator.change-requests.*') ? 'is-active' : '' }}"
                   title="Xem qua API — trang web đang xây dựng">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    <span>Change Requests</span>
                </a>
            </nav>
        </aside>

        <main class="operator-main">
            <header class="operator-topbar">
                <div>
                    <div class="operator-topbar-meta">Z.E.N.A — Procurement operator surface</div>
                    <div style="font-size:1.125rem;font-weight:600;color:#0f172a;">@yield('page_title', 'Operator Dashboard')</div>
                </div>
                <div class="operator-topbar-user">
                    {{ auth()->user()?->name ?? 'Operator' }}
                </div>
            </header>

            <div class="operator-content">
                <x-ui.toast />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
