@extends('layouts.operator')

@section('title', 'CR ' . $changeRequest->change_number)
@section('page_title', 'CR ' . $changeRequest->change_number)

@section('content')
    <x-ui.page-header
        :title="'CR ' . $changeRequest->change_number"
        :description="$changeRequest->title"
    >
        <x-ui.button-link :href="route('operator.change-requests.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Mô tả thay đổi">
                <div class="whitespace-pre-line text-slate-800">{{ $changeRequest->description }}</div>
            </x-ui.card>

            <x-ui.card title="Phân tích tác động">
                <div class="whitespace-pre-line text-slate-800">{{ $changeRequest->impact_analysis }}</div>
            </x-ui.card>

            <x-ui.card title="Lý do đề xuất">
                <div class="whitespace-pre-line text-slate-800">{{ $changeRequest->justification }}</div>
                @if ($changeRequest->alternatives_considered)
                    <div class="mt-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phương án thay thế đã cân nhắc</div>
                        <div class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $changeRequest->alternatives_considered }}</div>
                    </div>
                @endif
            </x-ui.card>

            @if ($changeRequest->rejection_reason)
                <x-ui.card title="Lý do từ chối">
                    <div class="whitespace-pre-line text-slate-800">{{ $changeRequest->rejection_reason }}</div>
                </x-ui.card>
            @endif

            @if ($changeRequest->status === 'submitted')
                <x-ui.card title="Xét duyệt">
                    @if ($errors->any())
                        <div class="operator-error-list">
                            <ul class="space-y-1 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-6">
                        <form method="POST" action="{{ route('operator.change-requests.approve', $changeRequest->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="approval_comments">Ý kiến phê duyệt (không bắt buộc)</label>
                                <textarea id="approval_comments" name="approval_comments" class="operator-textarea">{{ old('approval_comments') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
                        </form>

                        <hr class="border-gray-200">

                        <form method="POST" action="{{ route('operator.change-requests.reject', $changeRequest->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="rejection_reason">Lý do từ chối <span class="text-rose-600">*</span></label>
                                <textarea id="rejection_reason" name="rejection_reason" class="operator-textarea" required>{{ old('rejection_reason') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-secondary">Từ chối</button>
                        </form>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Thông tin">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái">
                        <x-ui.status-badge :status="$changeRequest->status" />
                    </x-ui.field-value>
                    <x-ui.field-value label="Dự án" :value="($changeRequest->project?->name ?? '—') . ($changeRequest->project?->code ? ' (' . $changeRequest->project->code . ')' : '')" />
                    <x-ui.field-value label="Loại thay đổi" :value="match($changeRequest->change_type) { 'scope' => 'Phạm vi', 'cost' => 'Chi phí', 'schedule' => 'Tiến độ', 'quality' => 'Chất lượng', 'design' => 'Thiết kế', default => 'Khác' }" />
                    <x-ui.field-value label="Mức ưu tiên" :value="match($changeRequest->priority) { 'urgent' => 'Khẩn cấp', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp', default => $changeRequest->priority }" />
                    <x-ui.field-value label="Tác động chi phí" :value="$changeRequest->cost_impact !== null ? number_format((float) $changeRequest->cost_impact) : null" />
                    <x-ui.field-value label="Tác động tiến độ" :value="$changeRequest->schedule_impact_days !== null ? $changeRequest->schedule_impact_days . ' ngày' : null" />
                    <x-ui.field-value label="Người đề xuất" :value="$changeRequest->requestedBy?->name" />
                    <x-ui.field-value label="Người phê duyệt" :value="$changeRequest->approvedBy?->name" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($changeRequest->created_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>

            @if ($changeRequest->status === 'draft')
                <x-ui.card title="Thao tác">
                    <form method="POST" action="{{ route('operator.change-requests.submit', $changeRequest->id) }}">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary w-full">Gửi duyệt</button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
