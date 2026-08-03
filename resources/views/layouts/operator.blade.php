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
    <div class="operator-shell">
        <aside class="operator-sidebar">
            <div class="operator-brand">
                <span class="operator-brand-kicker">Operator</span>
                <a href="{{ route('app.dashboard') }}" class="operator-brand-title">Procurement UI</a>
            </div>

            <nav class="operator-nav">
                @php($operatorNavSections = app(\App\Support\Navigation\OperatorNavigationComposer::class)->visibleFor(auth()->user()))
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
                <div>
                    <div class="operator-topbar-meta">Z.E.N.A — Procurement operator surface</div>
                    <div style="font-size:1.125rem;font-weight:600;color:#0f172a;">@yield('page_title', 'Operator Dashboard')</div>
                </div>
                <form method="GET" action="{{ route('operator.search.index') }}" style="flex:1;max-width:420px;margin:0 1.5rem;">
                    <input type="search" name="q" value="{{ request()->routeIs('operator.search.*') ? request('q') : '' }}"
                           class="operator-input" placeholder="Tìm kiếm dự án, RFI, hợp đồng, vật tư..." aria-label="Tìm kiếm toàn hệ thống">
                </form>
                <div class="operator-topbar-user" style="display:flex;align-items:center;gap:0.75rem;">
                    <span>{{ auth()->user()?->name ?? 'Operator' }}</span>
                    <form method="POST" action="{{ route('logout.post') }}">
                        @csrf
                        <button type="submit" class="operator-link" style="background:none;border:none;padding:0;cursor:pointer;font:inherit;">Đăng xuất</button>
                    </form>
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
