<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'ZenaManage'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js', 'resources/js/work-template-apply.js', 'resources/js/crm-pipeline-drag.js', 'resources/js/sidebar-scroll-restore.js'])
    @stack('styles')
</head>
<body>
    <a href="#main-content"
       class="skip-link sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-teal-700 focus:shadow-lg">
        Bỏ qua tới nội dung chính
    </a>
    @php
        $operatorNavSections = app(\App\Support\Navigation\OperatorNavigationComposer::class)->visibleFor(auth()->user());
        $operatorUserName = auth()->user()?->name ?? 'Operator';
        // GAP-118 H-1: initials of the first and last word, shown in the phone top bar.
        $operatorNameWords = preg_split('/\s+/u', trim($operatorUserName), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $operatorUserInitials = mb_strtoupper(mb_substr($operatorNameWords[0], 0, 1) . (count($operatorNameWords) > 1 ? mb_substr($operatorNameWords[count($operatorNameWords) - 1], 0, 1) : ''));
        $operatorSearchValue = request()->routeIs('operator.search.*') ? request('q') : '';
    @endphp
    <div class="operator-shell">
        <aside class="operator-sidebar">
            <div class="operator-brand">
                <span class="operator-brand-kicker">Operator</span>
                <a href="{{ route('app.dashboard') }}" class="operator-brand-title">Procurement UI</a>
            </div>

            <nav class="operator-nav">
                @foreach ($operatorNavSections as $section => $items)
                    <span class="operator-nav-section">{{ $section }}</span>
                    @foreach ($items as $item)
                        <a href="{{ route($item->routeName) }}"
                           class="operator-nav-link {{ request()->routeIs($item->routeName) ? 'is-active' : '' }}">
                            <x-operator-nav-icon :icon-key="$item->iconKey" />
                            <span>{{ $item->label }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>
        <script>
            // Khôi phục scrollTop của sidebar NGAY LẬP TỨC (đồng bộ, trước khi
            // trình duyệt vẽ), tránh chớp giật so với việc khôi phục bằng
            // module script (luôn chạy sau khi đã vẽ xong DOM ban đầu). Xem
            // resources/js/sidebar-scroll-restore.js cho phần ghi lại vị trí
            // cuộn khi người dùng cuộn sidebar.
            (function () {
                var saved = sessionStorage.getItem('operator-sidebar-scroll-top');
                if (saved === null) return;
                var sidebar = document.querySelector('.operator-sidebar');
                if (sidebar) sidebar.scrollTop = parseInt(saved, 10) || 0;
            })();
        </script>

        <main class="operator-main">
            <header class="operator-topbar">
                {{-- GAP-118 N-1 / H-1: on screens ≤1024px the sidebar is hidden and this menu replaces it (native <details>, works without JS). --}}
                <details class="operator-mobile-nav" data-operator-mobile-nav>
                    <summary class="operator-mobile-nav-toggle" aria-label="Mở menu"><span aria-hidden="true">☰</span> Menu</summary>
                    <div class="operator-mobile-nav-panel">
                        <form method="GET" action="{{ route('operator.search.index') }}">
                            <input type="search" name="q" value="{{ $operatorSearchValue }}" class="operator-input" placeholder="Tìm kiếm dự án, RFI, hợp đồng, vật tư..." aria-label="Tìm kiếm toàn hệ thống">
                        </form>
                        <div class="operator-mobile-nav-user">
                            <span>{{ $operatorUserName }}</span>
                            <form method="POST" action="{{ route('logout.post') }}">
                                @csrf
                                <button type="submit" class="operator-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;">Đăng xuất</button>
                            </form>
                        </div>
                        <nav class="operator-mobile-nav-links">
                            @foreach ($operatorNavSections as $section => $items)
                                <span class="operator-mobile-nav-section">{{ $section }}</span>
                                @foreach ($items as $item)
                                    <a href="{{ route($item->routeName) }}" class="operator-mobile-nav-link {{ request()->routeIs($item->routeName) ? 'is-active' : '' }}">
                                        <x-operator-nav-icon :icon-key="$item->iconKey" />
                                        <span>{{ $item->label }}</span>
                                    </a>
                                @endforeach
                            @endforeach
                        </nav>
                    </div>
                </details>
                <div class="operator-topbar-heading">
                    <div class="operator-topbar-meta operator-desktop-only">Z.E.N.A — Procurement operator surface</div>
                    <div class="operator-topbar-title">@yield('page_title', 'Operator Dashboard')</div>
                </div>
                <form method="GET" action="{{ route('operator.search.index') }}" class="operator-topbar-search operator-desktop-only">
                    <input type="search" name="q" value="{{ $operatorSearchValue }}"
                           class="operator-input" placeholder="Tìm kiếm dự án, RFI, hợp đồng, vật tư..." aria-label="Tìm kiếm toàn hệ thống">
                </form>
                <div class="operator-topbar-user operator-desktop-only" style="display:flex;align-items:center;gap:0.75rem;">
                    <span>{{ $operatorUserName }}</span>
                    <form method="POST" action="{{ route('logout.post') }}">
                        @csrf
                        <button type="submit" class="operator-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;">Đăng xuất</button>
                    </form>
                </div>
                <span class="operator-user-initials operator-mobile-only" title="{{ $operatorUserName }}">{{ $operatorUserInitials }}</span>
            </header>
            <script>
                // GAP-118 N-1: close the phone menu once a link is chosen (the page navigates anyway; this avoids a flash on back).
                (function () {
                    var nav = document.querySelector('[data-operator-mobile-nav]');
                    if (!nav) return;
                    nav.addEventListener('click', function (event) {
                        if (event.target.closest('a')) nav.removeAttribute('open');
                    });
                })();
            </script>

            <div class="operator-content" id="main-content">
                <x-ui.toast />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
