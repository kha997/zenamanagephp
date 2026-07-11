<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cổng khách hàng')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/operator.css'])
</head>
<body class="bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <header class="mb-8">
            <h1 class="text-xl font-semibold text-slate-900">Cổng khách hàng</h1>
        </header>
        <main>
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
