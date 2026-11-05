@extends('layouts.operator')

@section('title', 'Báo cáo giờ')
@section('page_title', 'Báo cáo giờ')

@section('content')
    {{-- GAP-112 P-1: hours only (no money), scoped by the viewer's rights. --}}
    @php
        $h = static fn (int $m): string => number_format($m / 60, 1, ',', '.');
        $name = static fn (array $row): string => $row['project'] ?? (($categories[$row['category']] ?? 'Không thuộc công trình') . ' (không thuộc công trình)');
    @endphp
    <x-ui.page-header title="Báo cáo giờ" :description="'Từ ' . $from->format('d/m/Y') . ' đến ' . $to->format('d/m/Y') . ' · tổng ' . $h($byProject['total_minutes']) . ' giờ'">
        <x-ui.button-link :href="route('app.time.report', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => 'csv'])" variant="secondary">Xuất CSV</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('app.time.report') }}" class="flex flex-wrap items-end gap-2 p-4">
            <label class="text-sm">Từ <input type="date" name="from" class="operator-input mt-1" value="{{ $from->toDateString() }}"></label>
            <label class="text-sm">Đến <input type="date" name="to" class="operator-input mt-1" value="{{ $to->toDateString() }}"></label>
            <button type="submit" class="operator-button operator-button-secondary">Xem</button>
        </form>
    </x-ui.card>

    <x-ui.card title="Theo công trình">
        @if ($byProject['rows'] === [])
            <p class="p-4 text-sm text-slate-500">Không có giờ trong khoảng này.</p>
        @else
            <x-ui.data-table :headers="['Công trình / loại việc', 'Người', 'Giờ đã duyệt', 'Giờ chờ duyệt']">
                @foreach ($byProject['rows'] as $row)
                    <tr><td class="text-sm">{{ $name($row) }}</td><td class="text-sm">{{ $row['person'] }}</td><td class="text-sm font-semibold">{{ $h($row['approved_minutes']) }}</td><td class="text-sm">{{ $h($row['pending_minutes']) }}</td></tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    <x-ui.card title="Theo người">
        @if ($byPerson['rows'] === [])
            <p class="p-4 text-sm text-slate-500">Không có giờ trong khoảng này.</p>
        @else
            <x-ui.data-table :headers="['Người', 'Công trình / loại việc', 'Giờ đã duyệt', 'Giờ chờ duyệt']">
                @foreach ($byPerson['rows'] as $row)
                    <tr><td class="text-sm">{{ $row['person'] }}</td><td class="text-sm">{{ $name($row) }}</td><td class="text-sm font-semibold">{{ $h($row['approved_minutes']) }}</td><td class="text-sm">{{ $h($row['pending_minutes']) }}</td></tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
