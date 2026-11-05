@extends('layouts.operator')

@section('title', 'Duyệt giờ')
@section('page_title', 'Duyệt giờ')

@section('content')
    {{-- GAP-112 R-1: weekly review of submitted time within the reviewer's scope. --}}
    @php $hm = static fn (int $m): string => intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT); @endphp
    <x-ui.page-header title="Duyệt giờ" :description="'Tuần ' . $week->format('d/m/Y') . ' – ' . $week->copy()->addDays(6)->format('d/m/Y')">
        <x-ui.button-link :href="route('app.time.review', ['week' => $week->copy()->subWeek()->toDateString()])" variant="secondary">← Tuần trước</x-ui.button-link>
        <x-ui.button-link :href="route('app.time.review', ['week' => $week->copy()->addWeek()->toDateString()])" variant="secondary">Tuần sau →</x-ui.button-link>
        <x-ui.button-link :href="route('app.time.index')" variant="secondary">Bảng giờ của tôi</x-ui.button-link>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    <x-ui.card>
        @if ($entries->isEmpty())
            <x-ui.empty-state title="Không có giờ chờ duyệt" description="Các dòng giờ đã gửi trong tuần thuộc phạm vi của bạn sẽ hiện ở đây." />
        @else
            <form method="POST" action="{{ route('app.time.review.act') }}" class="space-y-3 p-4" data-testid="time-review-form">
                @csrf
                <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                <x-ui.data-table :headers="['', 'Người', 'Ngày', 'Việc', 'Công trình', 'Thời lượng', 'Ghi chú']">
                    @foreach ($entries as $entry)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $entry->id }}" checked></td>
                            <td class="text-sm">{{ $entry->user?->name }}</td>
                            <td class="text-sm">{{ $entry->work_date->format('d/m') }}</td>
                            <td class="text-sm">{{ $entry->label() }}@if ($entry->auto_stopped)<span class="ml-1 rounded bg-amber-100 px-1 text-xs text-amber-800">Tự dừng</span>@endif @if ($entry->source === 'manual')<span class="ml-1 rounded bg-slate-100 px-1 text-xs">Nhập tay</span>@endif</td>
                            <td class="text-sm">{{ $entry->project?->name ?? '—' }}</td>
                            <td class="text-sm font-semibold">{{ $hm($entry->minutes) }}</td>
                            <td class="text-xs text-slate-600">{{ $entry->note }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
                <div class="flex flex-wrap items-end gap-2">
                    <button type="submit" name="action" value="approve" class="operator-button operator-button-primary">Duyệt các dòng đã chọn</button>
                    <input type="text" name="reason" maxlength="500" class="operator-input w-72" placeholder="Lý do từ chối">
                    <button type="submit" name="action" value="reject" class="operator-button operator-button-secondary">Từ chối</button>
                </div>
            </form>
        @endif
    </x-ui.card>
@endsection
