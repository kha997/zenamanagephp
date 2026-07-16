@extends('layouts.operator')

@section('title', 'RFI ' . $rfi->rfi_number)
@section('page_title', 'RFI ' . $rfi->rfi_number)

@section('content')
    <x-ui.page-header
        :title="'RFI ' . $rfi->rfi_number"
        :description="$rfi->title ?? $rfi->subject"
    >
        <x-ui.button-link :href="route('operator.rfis.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Câu hỏi">
                <div class="whitespace-pre-line text-slate-800">{{ $rfi->question ?? $rfi->description }}</div>
                @if ($rfi->location || $rfi->drawing_reference)
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @if ($rfi->location)
                            <x-ui.field-value label="Vị trí" :value="$rfi->location" />
                        @endif
                        @if ($rfi->drawing_reference)
                            <x-ui.field-value label="Bản vẽ tham chiếu" :value="$rfi->drawing_reference" />
                        @endif
                    </div>
                @endif
            </x-ui.card>

            @if ($rfi->response)
                <x-ui.card title="Phản hồi">
                    <div class="whitespace-pre-line text-slate-800">{{ $rfi->response }}</div>
                    <div class="mt-3 text-sm text-slate-500">
                        {{ $rfi->respondedBy?->name ?? '—' }} · {{ optional($rfi->responded_at)->format('d/m/Y H:i') ?? '' }}
                    </div>
                </x-ui.card>
            @elseif ($rfi->status === 'open' || $rfi->status === 'escalated')
                <x-ui.card title="Gửi phản hồi">
                    @if ($errors->any())
                        <div class="operator-error-list">
                            <ul class="space-y-1 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('operator.rfis.respond', $rfi->id) }}" class="space-y-4">
                        @csrf
                        <div class="operator-field">
                            <label for="response">Nội dung phản hồi <span class="text-rose-600">*</span></label>
                            <textarea id="response" name="response" class="operator-textarea" required>{{ old('response') }}</textarea>
                        </div>
                        <button type="submit" class="operator-button operator-button-primary">Gửi phản hồi</button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Thông tin">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái">
                        <x-ui.status-badge :status="$rfi->status" />
                    </x-ui.field-value>
                    <x-ui.field-value label="Dự án" :value="($rfi->project?->name ?? '—') . ($rfi->project?->code ? ' (' . $rfi->project->code . ')' : '')" />
                    <x-ui.field-value label="Mức ưu tiên" :value="match($rfi->priority) { 'urgent' => 'Khẩn cấp', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp', default => $rfi->priority }" />
                    <x-ui.field-value label="Người tạo" :value="$rfi->createdBy?->name ?? '—'" />
                    <x-ui.field-value label="Giao cho" :value="$rfi->assignedTo?->name ?? 'Chưa giao'" />
                    <x-ui.field-value label="Hạn trả lời" :value="optional($rfi->due_date)->format('d/m/Y') ?? '—'" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($rfi->created_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>

            @if ($rfi->status === 'answered')
                <x-ui.card title="Thao tác">
                    <form method="POST" action="{{ route('operator.rfis.close', $rfi->id) }}">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary w-full">Đóng RFI</button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
