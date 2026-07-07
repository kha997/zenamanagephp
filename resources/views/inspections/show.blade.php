@extends('layouts.operator')

@section('title', 'Kiểm định: ' . $inspection->title)
@section('page_title', $inspection->title)

@section('content')
    <x-ui.page-header
        :title="$inspection->title"
        :description="'Kế hoạch QC: ' . ($inspection->qcPlan?->title ?? '—')"
    >
        <x-ui.button-link :href="route('operator.inspections.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            @if ($inspection->description)
                <x-ui.card title="Mô tả">
                    <div class="whitespace-pre-line text-slate-800">{{ $inspection->description }}</div>
                </x-ui.card>
            @endif

            @if ($inspection->findings || $inspection->recommendations)
                <x-ui.card title="Kết quả kiểm định">
                    @if ($inspection->findings)
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phát hiện</div>
                        <div class="mt-1 whitespace-pre-line text-slate-800">{{ $inspection->findings }}</div>
                    @endif
                    @if ($inspection->recommendations)
                        <div class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Khuyến nghị</div>
                        <div class="mt-1 whitespace-pre-line text-slate-800">{{ $inspection->recommendations }}</div>
                    @endif
                </x-ui.card>
            @endif

            @if (in_array($inspection->status, ['scheduled', 'in_progress'], true))
                <x-ui.card :title="$inspection->status === 'scheduled' ? 'Thực hiện kiểm định' : 'Hoàn tất kiểm định'">
                    @if ($errors->any())
                        <div class="operator-error-list">
                            <ul class="space-y-1 text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ $inspection->status === 'scheduled' ? route('operator.inspections.conduct', $inspection->id) : route('operator.inspections.complete', $inspection->id) }}"
                          class="space-y-4">
                        @csrf
                        <div class="operator-field">
                            <label for="findings">Phát hiện</label>
                            <textarea id="findings" name="findings" class="operator-textarea">{{ old('findings', $inspection->findings) }}</textarea>
                        </div>
                        <div class="operator-field">
                            <label for="recommendations">Khuyến nghị</label>
                            <textarea id="recommendations" name="recommendations" class="operator-textarea">{{ old('recommendations', $inspection->recommendations) }}</textarea>
                        </div>
                        <button type="submit" class="operator-button operator-button-primary">
                            {{ $inspection->status === 'scheduled' ? 'Ghi nhận kiểm định' : 'Hoàn tất kiểm định' }}
                        </button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Thông tin">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái" :value="match($inspection->status) { 'scheduled' => 'Đã lên lịch', 'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn tất', default => $inspection->status }" />
                    <x-ui.field-value label="Kế hoạch QC" :value="$inspection->qcPlan?->title" />
                    <x-ui.field-value label="Người kiểm định" :value="$inspection->inspector?->name" />
                    <x-ui.field-value label="Ngày kiểm định" :value="optional($inspection->inspection_date)->format('d/m/Y')" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($inspection->created_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection
