@extends('layouts.operator')

@section('title', $item->name . ' — Công việc thiết kế')
@section('page_title', 'Chi tiết công việc thiết kế')

@php
    $statusLabels = [
        'draft' => 'Nháp', 'internal_review' => 'Đang duyệt nội bộ', 'sent_to_client' => 'Đã gửi khách',
        'revision_requested' => 'Khách yêu cầu sửa', 'approved' => 'Đã duyệt', 'final' => 'Hoàn tất',
    ];
    $evidenceLabels = ['phone' => 'Điện thoại', 'email' => 'Email', 'zalo' => 'Zalo', 'client_portal' => 'Cổng khách hàng'];
@endphp

@section('content')
    <x-ui.page-header title="{{ $item->name }}" description="Dự án: {{ $item->project?->name ?? '—' }}">
        <x-ui.button-link :href="route('operator.design-items.index')" variant="secondary">Bảng công việc</x-ui.button-link>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.card>
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Thông tin">
        <div class="operator-form-grid">
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$item->review_status" />
                <span class="ml-1 text-sm text-slate-600">{{ $statusLabels[$item->review_status] ?? '' }}</span>
            </x-ui.field-value>
            <x-ui.field-value label="Loại" :value="$item->item_type" />
            <x-ui.field-value label="Người phụ trách" :value="$item->assignee?->name ?? '—'" />
            <x-ui.field-value label="Hạn giao khách" :value="optional($item->due_to_client_at)->format('d/m/Y') ?? '—'" />
            @if ($item->client_feedback_notes)
                <x-ui.field-value label="Phản hồi khách" :value="$item->client_feedback_notes" />
            @endif
            @if ($item->approval_evidence)
                <x-ui.field-value label="Bằng chứng duyệt" :value="$evidenceLabels[$item->approval_evidence] ?? $item->approval_evidence" />
            @endif
        </div>
    </x-ui.card>

    {{-- Khối vướng mắc --}}
    @if (auth()->user()?->hasPermission('design-item.manage'))
        @if ($item->blocked_at)
            <div class="rounded border border-red-200 bg-red-50 p-4">
                <div class="mb-2 font-semibold text-red-700">Đang vướng</div>
                <p class="mb-2 text-sm text-red-800">{{ $item->blocker_note }}</p>
                <p class="mb-3 text-xs text-red-500">Ghi nhận lúc {{ optional($item->blocked_at)->format('d/m/Y H:i') }}</p>
                <form method="POST" action="{{ route('operator.design-items.unblock', $item->id) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">Gỡ vướng</button>
                </form>
            </div>
        @else
            <x-ui.card title="Báo vướng">
                <form method="POST" action="{{ route('operator.design-items.block', $item->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="operator-field flex-1 min-w-64">
                        <label for="blocker_note">Nội dung vướng mắc</label>
                        <textarea id="blocker_note" name="blocker_note" rows="2" maxlength="1000" class="operator-input" required placeholder="Mô tả vấn đề đang gặp...">{{ old('blocker_note') }}</textarea>
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Báo vướng</button>
                </form>
            </x-ui.card>
        @endif
    @elseif ($item->blocked_at)
        <div class="rounded border border-red-200 bg-red-50 p-4">
            <div class="mb-1 font-semibold text-red-700">Đang vướng</div>
            <p class="text-sm text-red-800">{{ $item->blocker_note }}</p>
        </div>
    @endif

    @if ($item->revisions->isNotEmpty())
        <x-ui.card title="Lịch sử chỉnh sửa ({{ $item->revision_count }} lần)">
            <ol class="space-y-3">
                @foreach ($item->revisions as $revision)
                    <li class="border-l-2 border-slate-200 pl-3">
                        <div class="text-sm font-medium">
                            Sửa lần {{ $revision->revision_no }}
                            — yêu cầu {{ $revision->requested_at->format('d/m/Y') }}
                            @if ($revision->requester) bởi {{ $revision->requester->name }} @endif
                            @if ($revision->resolved_at)
                                <span class="text-emerald-600">· đã xử lý {{ $revision->resolved_at->format('d/m/Y') }}</span>
                            @else
                                <span class="text-amber-600">· đang xử lý</span>
                            @endif
                        </div>
                        <div class="text-sm text-slate-600">{{ $revision->client_feedback }}</div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif

    @unless ($item->review_status === 'final')
        <x-ui.card title="Chuyển trạng thái">
            <form method="POST" action="{{ route('operator.design-items.status', $item->id) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="operator-field flex-1 min-w-64">
                    <label for="review_status">Trạng thái mới</label>
                    <select id="review_status" name="review_status" class="operator-select">
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($item->review_status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field flex-1 min-w-64">
                    <label for="client_feedback_notes">Phản hồi khách (bắt buộc nếu yêu cầu sửa)</label>
                    <input id="client_feedback_notes" name="client_feedback_notes" type="text" class="operator-input" value="{{ old('client_feedback_notes') }}">
                </div>
                <div class="operator-field flex-1 min-w-64">
                    <label for="approval_evidence">Bằng chứng duyệt (bắt buộc nếu duyệt)</label>
                    <select id="approval_evidence" name="approval_evidence" class="operator-select">
                        <option value="">--</option>
                        @foreach ($evidenceLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="operator-button operator-button-primary">Chuyển</button>
            </form>
        </x-ui.card>
    @endunless

    <x-ui.card title="File đính kèm">
        <form method="POST" action="{{ route('operator.design-items.documents.store', $item->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="operator-field flex-1 min-w-64">
                <label for="file">Chọn file</label>
                <input id="file" name="file" type="file" class="operator-input" required>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tải lên</button>
        </form>

        @if ($versions->isEmpty())
            <p class="mt-3 text-sm text-slate-400">Chưa có file nào.</p>
        @else
            <ul class="mt-3 space-y-1">
                @foreach ($versions as $version)
                    <li class="text-sm">Version {{ $version->version_number }} — {{ $version->creator?->name ?? '—' }} · {{ optional($version->created_at)->format('d/m/Y H:i') }}</li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="Lịch sử">
        @if ($events->isEmpty())
            <p class="text-sm text-slate-500">Chưa có sự kiện.</p>
        @else
            <ul class="space-y-2">
                @foreach ($events as $event)
                    <li class="text-sm">
                        <span class="font-medium text-slate-900">{{ $event->event_key }}</span>
                        <span class="text-slate-500">— {{ $event->actor?->name ?? 'Hệ thống' }} · {{ optional($event->occurred_at)->format('d/m/Y H:i') }}</span>
                        @if (($event->payload['from'] ?? null) && ($event->payload['to'] ?? null))
                            <span class="text-slate-500">({{ $statusLabels[$event->payload['from']] ?? $event->payload['from'] }} → {{ $statusLabels[$event->payload['to']] ?? $event->payload['to'] }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
