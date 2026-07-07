@extends('layouts.operator')

@section('title', 'Tạo phiên kiểm định')
@section('page_title', 'Tạo phiên kiểm định')

@section('content')
    <x-ui.page-header
        title="Tạo phiên kiểm định"
        description="Lên lịch phiên kiểm định mới từ kế hoạch QC."
    >
        <x-ui.button-link :href="route('operator.inspections.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin phiên kiểm định">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($qcPlans->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Chưa có kế hoạch QC nào trong tenant. Cần tạo kế hoạch QC trước khi lập phiên kiểm định.
            </div>
        @else
            <form method="POST" action="{{ route('operator.inspections.store') }}" class="space-y-5">
                @csrf

                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="qc_plan_id">Kế hoạch QC <span class="text-rose-600">*</span></label>
                        <select id="qc_plan_id" name="qc_plan_id" class="operator-select" required>
                            <option value="">Chọn kế hoạch</option>
                            @foreach ($qcPlans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('qc_plan_id') === (string) $plan->id)>{{ $plan->title }}</option>
                            @endforeach
                        </select>
                        @error('qc_plan_id')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                    </div>

                    <div class="operator-field">
                        <label for="inspector_id">Người kiểm định <span class="text-rose-600">*</span></label>
                        <select id="inspector_id" name="inspector_id" class="operator-select" required>
                            <option value="">Chọn người kiểm định</option>
                            @foreach ($inspectors as $inspector)
                                <option value="{{ $inspector->id }}" @selected(old('inspector_id') === (string) $inspector->id)>{{ $inspector->name }}</option>
                            @endforeach
                        </select>
                        @error('inspector_id')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                    </div>

                    <div class="operator-field">
                        <label for="inspection_date">Ngày kiểm định <span class="text-rose-600">*</span></label>
                        <input id="inspection_date" name="inspection_date" type="date" class="operator-input" value="{{ old('inspection_date') }}" required>
                        @error('inspection_date')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="operator-field">
                    <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                    <input id="title" name="title" type="text" class="operator-input" value="{{ old('title') }}" maxlength="255" required>
                    @error('title')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="description">Mô tả</label>
                    <textarea id="description" name="description" class="operator-textarea">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="operator-button operator-button-primary">Tạo phiên kiểm định</button>
            </form>
        @endif
    </x-ui.card>
@endsection
