@extends('layouts.operator')

@section('title', 'Submittal ' . $submittal->submittal_number)
@section('page_title', 'Submittal ' . $submittal->submittal_number)

@section('content')
    <x-ui.page-header
        :title="'Submittal ' . $submittal->submittal_number"
        :description="$submittal->title"
    >
        <x-ui.button-link :href="route('operator.submittals.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card title="Mô tả">
                <div class="whitespace-pre-line text-slate-800">{{ $submittal->description }}</div>
            </x-ui.card>

            @if ($submittal->approval_comments)
                <x-ui.card title="Ý kiến phê duyệt">
                    <div class="whitespace-pre-line text-slate-800">{{ $submittal->approval_comments }}</div>
                </x-ui.card>
            @endif

            @if ($submittal->rejection_reason)
                <x-ui.card title="Lý do từ chối">
                    <div class="whitespace-pre-line text-slate-800">{{ $submittal->rejection_reason }}</div>
                    @if ($submittal->rejection_comments)
                        <div class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $submittal->rejection_comments }}</div>
                    @endif
                </x-ui.card>
            @endif

            @if (in_array($submittal->status, ['submitted', 'pending_review'], true))
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
                        <form method="POST" action="{{ route('operator.submittals.approve', $submittal->id) }}" class="space-y-4">
                            @csrf
                            <div class="operator-field">
                                <label for="approval_comments">Ý kiến phê duyệt (không bắt buộc)</label>
                                <textarea id="approval_comments" name="approval_comments" class="operator-textarea">{{ old('approval_comments') }}</textarea>
                            </div>
                            <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
                        </form>

                        <hr class="border-gray-200">

                        <form method="POST" action="{{ route('operator.submittals.reject', $submittal->id) }}" class="space-y-4">
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
                        <x-ui.status-badge :status="$submittal->status" />
                    </x-ui.field-value>
                    <x-ui.field-value label="Dự án" :value="($submittal->project?->name ?? '—') . ($submittal->project?->code ? ' (' . $submittal->project->code . ')' : '')" />
                    <x-ui.field-value label="Loại hồ sơ" :value="match($submittal->submittal_type) { 'shop_drawing' => 'Shop drawing', 'material_sample' => 'Mẫu vật liệu', 'product_data' => 'Tài liệu sản phẩm', 'test_report' => 'Báo cáo thí nghiệm', default => 'Khác' }" />
                    <x-ui.field-value label="Mục spec" :value="$submittal->specification_section" />
                    <x-ui.field-value label="Nhà thầu" :value="$submittal->contractor" />
                    <x-ui.field-value label="Nhà sản xuất" :value="$submittal->manufacturer" />
                    <x-ui.field-value label="Người trình" :value="$submittal->submittedBy?->name" />
                    <x-ui.field-value label="Hạn duyệt" :value="optional($submittal->due_date)->format('d/m/Y')" />
                    <x-ui.field-value label="Ngày tạo" :value="optional($submittal->created_at)->format('d/m/Y H:i')" />
                </div>
            </x-ui.card>

            @if ($submittal->status === 'draft')
                <x-ui.card title="Thao tác">
                    <form method="POST" action="{{ route('operator.submittals.submit', $submittal->id) }}">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary w-full">Gửi duyệt</button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
