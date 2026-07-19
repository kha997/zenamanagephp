@extends('layouts.operator')

@section('title', 'Xuất báo cáo')
@section('page_title', 'Xuất báo cáo')

@section('content')
    <x-ui.page-header
        title="Xuất báo cáo"
        description="Xuất dữ liệu thật theo tenant ra file CSV (UTF-8, mở trực tiếp bằng Excel)."
    />

    <x-ui.card title="Chọn dữ liệu cần xuất">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.reports.export') }}" class="space-y-5">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="dataset">Loại dữ liệu</label>
                    <select id="dataset" name="dataset" class="operator-select" required>
                        @foreach ($datasets as $key => $label)
                            <option value="{{ $key }}" @selected(old('dataset') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="project_id">Dự án (tùy chọn, không áp dụng cho loại "Dự án")</label>
                    <select id="project_id" name="project_id" class="operator-select">
                        <option value="">Tất cả dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tải CSV</button>
        </form>
    </x-ui.card>

    <x-ui.card title="Báo cáo dòng tiền">
        <p class="mb-2 text-sm text-slate-600">Thu thực / chi thực / ròng / lũy kế theo tháng, kèm khoản chờ thu từ các hợp đồng.</p>
        <x-ui.button-link :href="route('operator.reports.cashflow')" variant="secondary">Mở Dòng tiền</x-ui.button-link>
    </x-ui.card>
@endsection
