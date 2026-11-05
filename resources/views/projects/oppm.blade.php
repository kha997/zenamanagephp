@extends('layouts.operator')

@section('title', $header['name'] . ' — OPPM một trang')
@section('page_title', 'OPPM một trang')

@section('content')
    <style>
        .oppm-bar-track { position: relative; height: 0.5rem; border-radius: 9999px; background: #e2e8f0; }
        .oppm-bar { position: absolute; top: 0; height: 0.5rem; border-radius: 9999px; background: #0f766e; }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { font-size: 10px; }
            nav, aside, header, footer, .oppm-no-print { display: none !important; }
            .operator-panel { break-inside: avoid; box-shadow: none; }
            .oppm-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    @php $generatedAt = $oppm['generated_at'] instanceof \DateTimeInterface ? $oppm['generated_at']->format('d/m/Y H:i') : (string) $oppm['generated_at']; @endphp
    <x-ui.page-header
        title="{{ $header['name'] }}"
        description="OPPM một trang — {{ $header['code'] ?? '' }} · {{ $snapshot ? 'bản chốt kỳ ' . $snapshot->period_label . ', lập lúc' : 'lập lúc' }} {{ $generatedAt }}"
    >
        <div class="oppm-no-print flex flex-wrap gap-2">
            <x-ui.button-link :href="route('app.projects.show', $header['id'])" variant="secondary">Về trang dự án</x-ui.button-link>
            @if ($snapshot)
                <x-ui.button-link :href="route('app.projects.oppm', $header['id'])" variant="secondary">OPPM hiện tại</x-ui.button-link>
                <x-ui.button-link :href="route('app.projects.oppm.snapshots.compare', [$header['id'], $snapshot->id])" variant="secondary">So với kỳ trước</x-ui.button-link>
            @endif
            <x-ui.button-link :href="route('app.projects.oppm.snapshots', $header['id'])" variant="secondary">Lịch sử báo cáo</x-ui.button-link>
            <button type="button" class="operator-button operator-button-secondary" onclick="window.print()">In</button>
        </div>
    </x-ui.page-header>

    @if (session('success'))
        <p class="oppm-no-print mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="oppm-no-print mb-3 space-y-1 text-sm text-red-700">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    @if ($snapshot)
        <p class="mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" data-testid="oppm-snapshot-banner">
            Bản chốt kỳ <strong>{{ $snapshot->period_label }}</strong> ngày {{ $snapshot->created_at->format('d/m/Y H:i') }} — số liệu đúng như lúc chốt, không thay đổi theo dữ liệu hiện tại.
        </p>
    @elseif ($canEdit)
        <form method="POST" action="{{ route('app.projects.oppm.snapshots.store', $header['id']) }}" class="oppm-no-print mb-3 flex flex-wrap items-end gap-2" data-testid="oppm-close-form">
            @csrf
            <label class="text-sm">Tên kỳ <input type="text" name="period_label" maxlength="100" required class="operator-input mt-1 w-56" placeholder="Ví dụ: Tuần 45/2026"></label>
            <button type="submit" class="operator-button operator-button-secondary" onclick="return confirm('Chốt báo cáo kỳ này? Bản chốt không sửa được.')">Chốt báo cáo</button>
        </form>
    @endif

    @include('projects._oppm-body')
@endsection
