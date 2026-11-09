@extends('layouts.operator')

@section('title', $project->name . ' — Mốc tiến độ')
@section('page_title', 'Mốc tiến độ')

@section('content')
    {{-- GAP-117 L-1, E-1, A-1: milestones of one project; writes need the project PM or project.update. "Quá hạn" is live. --}}
    @php
        $badge = static function (\App\Models\ProjectMilestone $m): array {
            if ($m->status === \App\Models\ProjectMilestone::STATUS_COMPLETED) {
                return ['Xong', 'bg-emerald-100 text-emerald-700'];
            }
            if ($m->status === \App\Models\ProjectMilestone::STATUS_CANCELLED) {
                return ['Đã huỷ', 'bg-slate-200 text-slate-600'];
            }

            return $m->isOverdue() ? ['Quá hạn', 'bg-rose-100 text-rose-700'] : ['Đang chờ', 'bg-sky-100 text-sky-700'];
        };
        $today = now()->toDateString();
    @endphp
    <x-ui.page-header :title="'Mốc tiến độ — ' . $project->name" description="Các mốc quan trọng của dự án, ngày mục tiêu và tình trạng.">
        <x-ui.button-link :href="route('app.projects.show', $project->id)" variant="secondary">Về trang dự án</x-ui.button-link>
        <x-ui.button-link :href="route('app.projects.oppm', $project->id)" variant="secondary">OPPM một trang</x-ui.button-link>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    @if ($canEdit)
        <x-ui.card title="Thêm mốc">
            <form method="POST" action="{{ route('app.projects.milestones.store', $project->id) }}" class="grid gap-3 p-4 md:grid-cols-4" data-testid="milestone-create-form">
                @csrf
                <label class="text-sm md:col-span-2">Tên mốc <span class="text-rose-600">*</span>
                    <input type="text" name="name" maxlength="255" required class="operator-input mt-1" value="{{ old('name') }}" placeholder="Ví dụ: Xong phần móng">
                </label>
                <label class="text-sm">Ngày mục tiêu <span class="text-rose-600">*</span>
                    <input type="date" name="target_date" required class="operator-input mt-1" value="{{ old('target_date') }}">
                </label>
                <label class="text-sm">Thứ tự
                    <input type="number" name="order" min="0" max="999" class="operator-input mt-1" value="{{ old('order') }}">
                </label>
                <label class="text-sm md:col-span-4">Mô tả
                    <textarea name="description" maxlength="2000" rows="2" class="operator-input mt-1">{{ old('description') }}</textarea>
                </label>
                <button type="submit" class="operator-button operator-button-primary md:col-span-4">Thêm mốc</button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card>
        @if ($milestones->isEmpty())
            <x-ui.empty-state title="Chưa có mốc" description="{{ $canEdit ? 'Thêm mốc đầu tiên ở biểu mẫu phía trên.' : 'Dự án chưa có mốc tiến độ.' }}" />
        @else
            <x-ui.data-table :headers="['#', 'Mốc', 'Ngày mục tiêu', 'Tình trạng', 'Ngày xong / lý do huỷ', '']">
                @foreach ($milestones as $row)
                    @php [$label, $classes] = $badge($row); @endphp
                    <tr data-testid="milestone-row">
                        <td class="text-sm text-slate-500">{{ $row->order }}</td>
                        <td class="text-sm">
                            <span class="font-medium text-slate-900">{{ $row->name }}</span>
                            @if ($row->description)<div class="whitespace-pre-line text-xs text-slate-500">{{ $row->description }}</div>@endif
                        </td>
                        <td class="text-sm">{{ $row->target_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-sm"><span class="rounded px-2 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span></td>
                        <td class="text-sm">
                            @if ($row->status === \App\Models\ProjectMilestone::STATUS_COMPLETED)
                                {{ $row->completed_date?->format('d/m/Y') ?? '—' }}
                            @elseif ($row->status === \App\Models\ProjectMilestone::STATUS_CANCELLED)
                                {{ $row->metadata['cancelled_reason'] ?? '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-sm">
                            @if ($canEdit)
                                <details data-testid="milestone-actions">
                                    <summary class="operator-link text-xs">Xử lý</summary>
                                    @if ($row->status === \App\Models\ProjectMilestone::STATUS_COMPLETED || $row->status === \App\Models\ProjectMilestone::STATUS_CANCELLED)
                                        <form method="POST" action="{{ route('app.projects.milestones.reopen', [$project->id, $row->id]) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="operator-button operator-button-secondary">Mở lại</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('app.projects.milestones.complete', [$project->id, $row->id]) }}" class="mt-2 grid gap-1">
                                            @csrf
                                            <input type="date" name="completed_date" max="{{ $today }}" class="operator-input" value="{{ $today }}">
                                            <button type="submit" class="operator-button operator-button-primary">Đánh dấu xong</button>
                                        </form>
                                        <form method="POST" action="{{ route('app.projects.milestones.update', [$project->id, $row->id]) }}" class="mt-2 grid gap-1">
                                            @csrf
                                            <input type="text" name="name" maxlength="255" required class="operator-input" value="{{ $row->name }}">
                                            <input type="date" name="target_date" required class="operator-input" value="{{ $row->target_date?->toDateString() }}">
                                            <input type="number" name="order" min="0" max="999" class="operator-input" value="{{ $row->order }}">
                                            <textarea name="description" maxlength="2000" rows="2" class="operator-input">{{ $row->description }}</textarea>
                                            <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
                                        </form>
                                        <form method="POST" action="{{ route('app.projects.milestones.cancel', [$project->id, $row->id]) }}" class="mt-2 grid gap-1">
                                            @csrf
                                            <input type="text" name="reason" maxlength="500" required class="operator-input" placeholder="Lý do huỷ">
                                            <button type="submit" class="operator-button operator-button-secondary">Huỷ mốc</button>
                                        </form>
                                    @endif
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
