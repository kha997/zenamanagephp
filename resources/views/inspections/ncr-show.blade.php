@extends('layouts.operator')

@section('title', 'NCR ' . $ncr->ncr_number)
@section('page_title', 'NCR ' . $ncr->ncr_number)

@section('content')
    <x-ui.page-header
        :title="'NCR ' . $ncr->ncr_number"
        :description="$ncr->title"
    >
        <x-ui.button-link :href="route('operator.inspections.show', $inspectionId)" variant="secondary">Quay lại phiên kiểm định</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Mô tả điểm không phù hợp">
                <div class="whitespace-pre-line text-slate-800">{{ $ncr->description }}</div>
            </x-ui.card>

            @if ($ncr->root_cause || $ncr->corrective_action || $ncr->preventive_action || $ncr->resolution)
                <x-ui.card title="Phân tích và xử lý">
                    <div class="space-y-4">
                        @if ($ncr->root_cause)
                            <x-ui.field-value label="Nguyên nhân gốc" :value="$ncr->root_cause" />
                        @endif
                        @if ($ncr->corrective_action)
                            <x-ui.field-value label="Hành động khắc phục" :value="$ncr->corrective_action" />
                        @endif
                        @if ($ncr->preventive_action)
                            <x-ui.field-value label="Hành động phòng ngừa" :value="$ncr->preventive_action" />
                        @endif
                        @if ($ncr->resolution)
                            <x-ui.field-value label="Kết quả xử lý" :value="$ncr->resolution" />
                        @endif
                    </div>
                </x-ui.card>
            @endif

            @php
                $nextStatus = match ($ncr->status) {
                    'open' => 'in_progress',
                    'in_progress' => 'resolved',
                    'resolved' => 'closed',
                    default => null,
                };
                $nextLabel = match ($nextStatus) {
                    'in_progress' => 'Bắt đầu xử lý',
                    'resolved' => 'Đánh dấu đã khắc phục',
                    'closed' => 'Đóng NCR',
                    default => null,
                };
            @endphp

            @if ($nextStatus !== null)
                <x-ui.card :title="$nextLabel">
                    @if ($errors->any())
                        <div class="operator-error-list">
                            <ul class="space-y-1 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('operator.inspections.ncrs.update-status', ['inspection' => $inspectionId, 'ncr' => $ncr->id]) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="status" value="{{ $nextStatus }}">

                        @if ($nextStatus === 'resolved')
                            <div class="operator-field">
                                <label for="root_cause">Nguyên nhân gốc</label>
                                <textarea id="root_cause" name="root_cause" class="operator-textarea">{{ old('root_cause', $ncr->root_cause) }}</textarea>
                            </div>
                            <div class="operator-field">
                                <label for="corrective_action">Hành động khắc phục</label>
                                <textarea id="corrective_action" name="corrective_action" class="operator-textarea">{{ old('corrective_action', $ncr->corrective_action) }}</textarea>
                            </div>
                            <div class="operator-field">
                                <label for="resolution">Kết quả xử lý</label>
                                <textarea id="resolution" name="resolution" class="operator-textarea">{{ old('resolution', $ncr->resolution) }}</textarea>
                            </div>
                        @endif

                        <button type="submit" class="operator-button operator-button-primary">{{ $nextLabel }}</button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Thông tin">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái" :value="match($ncr->status) { 'open' => 'Đang mở', 'in_progress' => 'Đang xử lý', 'resolved' => 'Đã khắc phục', 'closed' => 'Đã đóng', default => $ncr->status }" />
                    <x-ui.field-value label="Mức độ" :value="match($ncr->severity) { 'critical' => 'Nghiêm trọng', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp', default => $ncr->severity }" />
                    <x-ui.field-value label="Phiên kiểm định" :value="$ncr->inspection?->title" />
                    <x-ui.field-value label="Người tạo" :value="$ncr->creator?->name" />
                    <x-ui.field-value label="Giao cho" :value="$ncr->assignee?->name ?? 'Chưa giao'" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($ncr->created_at)->format('d/m/Y H:i')" />
                    <x-ui.field-value label="Ngày khắc phục" :value="optional($ncr->resolved_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection
