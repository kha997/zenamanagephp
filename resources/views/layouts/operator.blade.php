<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'ZenaManage'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/operator.css', 'resources/js/money-format.js'])
    @stack('styles')
</head>
<body>
    <a href="#main-content"
       class="skip-link sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-teal-700 focus:shadow-lg">
        Bỏ qua tới nội dung chính
    </a>
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
                <a href="{{ route('operator.activity-feed.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.activity-feed.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>Nhật ký hoạt động</span>
                </a>
                <a href="{{ route('operator.schedule.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.schedule.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" /></svg>
                    <span>Tiến độ dự án</span>
                </a>

                <span class="operator-nav-section">Kinh doanh</span>
                <a href="{{ route('operator.crm.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.crm.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>CRM</span>
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
                <a href="{{ route('operator.materials.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.materials.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                    <span>Vật tư</span>
                </a>
                <a href="{{ route('operator.vendors.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.vendors.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    <span>Nhà cung cấp</span>
                </a>

                <span class="operator-nav-section">Thương mại</span>
                <a href="{{ route('operator.boqs.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.boqs.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                    <span>BOQ</span>
                </a>
                <a href="{{ route('operator.contracts.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.contracts.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Hợp đồng</span>
                </a>

                <span class="operator-nav-section">Dự án</span>
                <a href="{{ route('app.projects') }}"
                   class="operator-nav-link {{ request()->routeIs('app.projects*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    <span>Dự án</span>
                </a>
                <a href="{{ route('app.tasks') }}"
                   class="operator-nav-link {{ request()->routeIs('app.tasks*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                    <span>Công việc</span>
                </a>
                <a href="{{ route('operator.design-items.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.design-items.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Công việc thiết kế</span>
                </a>
                <a href="{{ route('app.calendar') }}"
                   class="operator-nav-link {{ request()->routeIs('app.calendar') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span>Lịch</span>
                </a>
                <a href="{{ route('app.team.index') }}"
                   class="operator-nav-link {{ request()->routeIs('app.team*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>Nhóm</span>
                </a>

                <span class="operator-nav-section">Công trường</span>
                <a href="{{ route('operator.site-diaries.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.site-diaries.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span>Nhật ký công trường</span>
                </a>

                <span class="operator-nav-section">Chất lượng</span>
                <a href="{{ route('operator.inspections.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.inspections.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    <span>Kiểm định</span>
                </a>

                <span class="operator-nav-section">Tài liệu</span>
                <a href="{{ route('operator.rfis.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.rfis.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>RFI</span>
                </a>
                <a href="{{ route('operator.submittals.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.submittals.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Submittals</span>
                </a>
                <a href="{{ route('operator.change-requests.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.change-requests.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    <span>Change Requests</span>
                </a>

                <span class="operator-nav-section">Hệ thống</span>
                <a href="{{ route('operator.reports.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.reports.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Xuất báo cáo</span>
                </a>
                <a href="{{ route('operator.webhooks.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.webhooks.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                    <span>Webhooks</span>
                </a>
                <a href="{{ route('operator.api-tokens.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.api-tokens.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                    <span>API Tokens</span>
                </a>
            </nav>
        </aside>

        <main class="operator-main">
            <header class="operator-topbar">
                <div>
                    <div class="operator-topbar-meta">Z.E.N.A — Procurement operator surface</div>
                    <div style="font-size:1.125rem;font-weight:600;color:#0f172a;">@yield('page_title', 'Operator Dashboard')</div>
                </div>
                <form method="GET" action="{{ route('operator.search.index') }}" style="flex:1;max-width:420px;margin:0 1.5rem;">
                    <input type="search" name="q" value="{{ request()->routeIs('operator.search.*') ? request('q') : '' }}"
                           class="operator-input" placeholder="Tìm kiếm dự án, RFI, hợp đồng, vật tư..." aria-label="Tìm kiếm toàn hệ thống">
                </form>
                <div class="operator-topbar-user">
                    {{ auth()->user()?->name ?? 'Operator' }}
                </div>
            </header>

            <div class="operator-content" id="main-content">
                <x-ui.toast />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
