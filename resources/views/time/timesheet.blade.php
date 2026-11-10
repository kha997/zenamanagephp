@extends('layouts.operator')

@section('title', 'Bảng giờ của tôi')
@section('page_title', 'Bảng giờ của tôi')

@section('content')
    {{-- GAP-112 T-1 / M-1 / C-1: own time entries of one week. --}}
    @php
        $hm = static fn (int $m): string => intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
        $weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    @endphp
    <x-ui.page-header title="Bảng giờ của tôi" :description="'Tuần ' . $week->format('d/m/Y') . ' – ' . $week->copy()->addDays(6)->format('d/m/Y') . ' · tổng ' . $hm($total)">
        {{-- GAP-118: let the header buttons wrap on a phone instead of squeezing. --}}
        <div class="flex flex-wrap items-center gap-3">
        <x-ui.button-link :href="route('app.time.index', ['week' => $week->copy()->subWeek()->toDateString()])" variant="secondary">← Tuần trước</x-ui.button-link>
        <x-ui.button-link :href="route('app.time.index', ['week' => $week->copy()->addWeek()->toDateString()])" variant="secondary">Tuần sau →</x-ui.button-link>
        @if ($canReview)
            <a href="{{ route('app.time.review') }}" class="operator-button operator-button-secondary" data-testid="time-review-link">Duyệt giờ</a>
        @endif
        @if ($canReport)
            <a href="{{ route('app.time.report') }}" class="operator-button operator-button-secondary">Báo cáo giờ</a>
        @endif
        </div>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    <x-ui.card title="Đồng hồ">
        <div class="flex flex-wrap items-end gap-3 p-4">
            @if ($running)
                <p class="text-sm">Đang ghi giờ: <strong>{{ $running->label() }}</strong> từ {{ $running->started_at?->format('H:i') }}</p>
                <form method="POST" action="{{ route('app.time.stop') }}" data-testid="time-stop-form">@csrf<button type="submit" class="operator-button operator-button-primary">Dừng</button></form>
            @endif
            <form method="POST" action="{{ route('app.time.start') }}" class="flex flex-wrap items-end gap-2" data-testid="time-start-form">
                @csrf
                <label class="text-sm">Công việc
                    <select name="task_id" class="operator-input mt-1">
                        <option value="">—</option>
                        @foreach ($tasks as $task)
                            <option value="{{ $task->id }}">{{ $task->name ?? $task->title }} ({{ $task->project?->name }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">hoặc việc không thuộc công trình
                    <select name="category" class="operator-input mt-1">
                        <option value="">—</option>
                        @foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <input type="text" name="note" maxlength="500" class="operator-input" placeholder="Ghi chú">
                <button type="submit" class="operator-button operator-button-secondary">Bắt đầu</button>
            </form>
        </div>
    </x-ui.card>

    <x-ui.card title="Tổng theo ngày">
        <div class="grid grid-cols-7 gap-2 p-4 text-center text-sm">
            @foreach (array_values($days) as $i => $minutes)
                <div><div class="text-xs text-slate-500">{{ $weekdays[$i] }} {{ $week->copy()->addDays($i)->format('d/m') }}</div><div class="font-semibold">{{ $hm($minutes) }}</div></div>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card title="Các dòng giờ">
        @if ($entries->isEmpty())
            <x-ui.empty-state title="Chưa có giờ trong tuần" description="Bấm Bắt đầu trên một công việc hoặc nhập tay bên dưới." />
        @else
            <div class="operator-wide-only">
            <x-ui.data-table :headers="['Ngày', 'Việc', 'Công trình', 'Giờ', 'Thời lượng', 'Trạng thái', 'Ghi chú', '']">
                @foreach ($entries as $entry)
                    <tr data-testid="time-entry-row">
                        <td class="text-sm">{{ $entry->work_date->format('d/m') }}</td>
                        <td class="text-sm">{{ $entry->label() }}</td>
                        <td class="text-sm">{{ $entry->project?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $entry->started_at ? $entry->started_at->format('H:i') . '–' . ($entry->ended_at?->format('H:i') ?? '…') : '—' }}</td>
                        <td class="text-sm font-semibold">{{ $hm($entry->minutes) }}</td>
                        <td class="text-sm">
                            {{ $statuses[$entry->status] ?? $entry->status }}
                            @if ($entry->auto_stopped)<span class="ml-1 rounded bg-amber-100 px-1 text-xs text-amber-800">Tự dừng</span>@endif
                            @if ($entry->status === 'rejected' && $entry->review_note)<div class="text-xs text-rose-700">{{ $entry->review_note }}</div>@endif
                        </td>
                        <td class="text-xs text-slate-600">{{ $entry->note }}</td>
                        <td class="text-sm">
                            @if (in_array($entry->status, ['submitted', 'rejected'], true))
                                <details>
                                    <summary class="operator-link text-xs">Sửa / xoá</summary>
                                    <form method="POST" action="{{ route('app.time.entries.update', $entry->id) }}" class="mt-2 grid gap-1">
                                        @csrf
                                        <input type="hidden" name="task_id" value="{{ $entry->task_id }}">
                                        <input type="hidden" name="category" value="{{ $entry->category }}">
                                        <input type="date" name="work_date" class="operator-input" value="{{ $entry->work_date->toDateString() }}">
                                        @if ($entry->started_at)
                                            <input type="time" name="start_time" class="operator-input" value="{{ $entry->started_at->format('H:i') }}">
                                            <input type="time" name="end_time" class="operator-input" value="{{ $entry->ended_at?->format('H:i') }}">
                                        @else
                                            <input type="number" name="minutes" min="1" max="1440" class="operator-input" value="{{ $entry->minutes }}">
                                        @endif
                                        <input type="text" name="note" maxlength="500" required class="operator-input" placeholder="Lý do sửa" value="">
                                        <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
                                    </form>
                                    <form method="POST" action="{{ route('app.time.entries.delete', $entry->id) }}" class="mt-1" onsubmit="return confirm('Xoá dòng giờ này?')">@csrf<button type="submit" class="operator-button operator-button-inline">Xoá</button></form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            </div>
            {{-- GAP-118 C-1: one card per time entry below 768px, same details and edit forms as the table. --}}
            <div class="operator-phone-only"><div class="operator-card-list">
                @foreach ($entries as $entry)
                    <div class="operator-item-card" data-testid="time-entry-card">
                        <div class="flex items-start justify-between gap-2">
                            <span class="operator-item-card-title">{{ $entry->label() }}</span>
                            <span class="font-semibold">{{ $hm($entry->minutes) }}</span>
                        </div>
                        <div class="operator-item-card-meta">{{ $entry->work_date->format('d/m') }} · {{ $entry->started_at ? $entry->started_at->format('H:i') . '–' . ($entry->ended_at?->format('H:i') ?? '…') : 'nhập tay' }} · {{ $entry->project?->name ?? 'Không thuộc công trình' }}</div>
                        <div>
                            {{ $statuses[$entry->status] ?? $entry->status }}
                            @if ($entry->auto_stopped)<span class="ml-1 rounded bg-amber-100 px-1 text-xs text-amber-800">Tự dừng</span>@endif
                            @if ($entry->status === 'rejected' && $entry->review_note)<div class="text-xs text-rose-700">{{ $entry->review_note }}</div>@endif
                        </div>
                        @if ($entry->note)<div class="text-xs text-slate-600">{{ $entry->note }}</div>@endif
                        @if (in_array($entry->status, ['submitted', 'rejected'], true))
                            <details>
                                <summary class="operator-link text-xs">Sửa / xoá</summary>
                                <form method="POST" action="{{ route('app.time.entries.update', $entry->id) }}" class="mt-2 grid gap-1">
                                    @csrf
                                    <input type="hidden" name="task_id" value="{{ $entry->task_id }}">
                                    <input type="hidden" name="category" value="{{ $entry->category }}">
                                    <input type="date" name="work_date" class="operator-input" value="{{ $entry->work_date->toDateString() }}">
                                    @if ($entry->started_at)
                                        <input type="time" name="start_time" class="operator-input" value="{{ $entry->started_at->format('H:i') }}">
                                        <input type="time" name="end_time" class="operator-input" value="{{ $entry->ended_at?->format('H:i') }}">
                                    @else
                                        <input type="number" name="minutes" min="1" max="1440" class="operator-input" value="{{ $entry->minutes }}">
                                    @endif
                                    <input type="text" name="note" maxlength="500" required class="operator-input" placeholder="Lý do sửa" value="">
                                    <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
                                </form>
                                <form method="POST" action="{{ route('app.time.entries.delete', $entry->id) }}" class="mt-1" onsubmit="return confirm('Xoá dòng giờ này?')">@csrf<button type="submit" class="operator-button operator-button-inline">Xoá</button></form>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div></div>
        @endif
    </x-ui.card>

    <x-ui.card title="Nhập giờ tay">
        <form method="POST" action="{{ route('app.time.entries.store') }}" class="grid gap-3 p-4 md:grid-cols-3" data-testid="time-manual-form">
            @csrf
            <label class="text-sm">Công việc
                <select name="task_id" class="operator-input mt-1">
                    <option value="">—</option>
                    @foreach ($tasks as $task)<option value="{{ $task->id }}" @selected(old('task_id') === $task->id)>{{ $task->name ?? $task->title }} ({{ $task->project?->name }})</option>@endforeach
                </select>
            </label>
            <label class="text-sm">hoặc việc không thuộc công trình
                <select name="category" class="operator-input mt-1">
                    <option value="">—</option>
                    @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Ngày <input type="date" name="work_date" required class="operator-input mt-1" value="{{ old('work_date', now()->toDateString()) }}"></label>
            <label class="text-sm">Từ <input type="time" name="start_time" class="operator-input mt-1" value="{{ old('start_time') }}"></label>
            <label class="text-sm">Đến <input type="time" name="end_time" class="operator-input mt-1" value="{{ old('end_time') }}"></label>
            <label class="text-sm">hoặc số phút <input type="number" name="minutes" min="1" max="1440" class="operator-input mt-1" value="{{ old('minutes') }}"></label>
            <label class="text-sm md:col-span-3">Lý do <span class="text-rose-600">*</span> <input type="text" name="note" maxlength="500" required class="operator-input mt-1" value="{{ old('note') }}"></label>
            <button type="submit" class="operator-button operator-button-primary md:col-span-3">Thêm</button>
        </form>
    </x-ui.card>

    @include('time._running-bar', ['running' => $running])
@endsection
