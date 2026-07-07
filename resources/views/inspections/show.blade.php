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

    <div class="mt-6 space-y-6">
        <x-ui.card title="NCR — Điểm không phù hợp ({{ $ncrs->count() }})">
            @if ($ncrs->isEmpty())
                <div class="py-4 text-center text-sm text-slate-500">Chưa ghi nhận NCR nào cho phiên kiểm định này.</div>
            @else
                <x-ui.data-table :headers="['Số NCR', 'Tiêu đề', 'Mức độ', 'Trạng thái', 'Giao cho', 'Thao tác']">
                    @foreach ($ncrs as $ncr)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $ncr->ncr_number }}</td>
                            <td class="font-medium text-slate-900">{{ $ncr->title }}</td>
                            <td>
                                @php
                                    $sevClasses = match ($ncr->severity) {
                                        'critical' => 'bg-rose-100 text-rose-800',
                                        'high' => 'bg-amber-100 text-amber-800',
                                        'medium' => 'bg-sky-100 text-sky-800',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                    $sevLabel = match ($ncr->severity) {
                                        'critical' => 'Nghiêm trọng', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp', default => $ncr->severity,
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $sevClasses }}">{{ $sevLabel }}</span>
                            </td>
                            <td>
                                @php
                                    $ncrStatusClasses = match ($ncr->status) {
                                        'open' => 'bg-rose-100 text-rose-800',
                                        'in_progress' => 'bg-amber-100 text-amber-800',
                                        'resolved' => 'bg-emerald-100 text-emerald-800',
                                        'closed' => 'bg-slate-200 text-slate-600',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                    $ncrStatusLabel = match ($ncr->status) {
                                        'open' => 'Đang mở', 'in_progress' => 'Đang xử lý', 'resolved' => 'Đã khắc phục', 'closed' => 'Đã đóng', default => $ncr->status,
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $ncrStatusClasses }}">{{ $ncrStatusLabel }}</span>
                            </td>
                            <td class="text-sm text-slate-600">{{ $ncr->assignee?->name ?? '—' }}</td>
                            <td>
                                <a href="{{ route('operator.inspections.ncrs.show', ['inspection' => $inspection->id, 'ncr' => $ncr->id]) }}" class="operator-button operator-button-inline">Chi tiết</a>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>

        <x-ui.card title="Ghi nhận NCR mới">
            <form method="POST" action="{{ route('operator.inspections.ncrs.store', $inspection->id) }}" class="space-y-5">
                @csrf

                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="ncr_severity">Mức độ</label>
                        <select id="ncr_severity" name="severity" class="operator-select">
                            <option value="low" @selected(old('severity') === 'low')>Thấp</option>
                            <option value="medium" @selected(old('severity', 'medium') === 'medium')>Trung bình</option>
                            <option value="high" @selected(old('severity') === 'high')>Cao</option>
                            <option value="critical" @selected(old('severity') === 'critical')>Nghiêm trọng</option>
                        </select>
                    </div>

                    <div class="operator-field">
                        <label for="ncr_assigned_to">Giao cho</label>
                        <select id="ncr_assigned_to" name="assigned_to" class="operator-select">
                            <option value="">Chưa giao</option>
                            @foreach ($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected(old('assigned_to') === (string) $assignee->id)>{{ $assignee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="operator-field">
                    <label for="ncr_title">Tiêu đề <span class="text-rose-600">*</span></label>
                    <input id="ncr_title" name="title" type="text" class="operator-input" value="{{ old('title') }}" maxlength="255" required>
                </div>

                <div class="operator-field">
                    <label for="ncr_description">Mô tả điểm không phù hợp <span class="text-rose-600">*</span></label>
                    <textarea id="ncr_description" name="description" class="operator-textarea" required>{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="operator-button operator-button-primary">Ghi nhận NCR</button>
            </form>
        </x-ui.card>
    </div>
@endsection
