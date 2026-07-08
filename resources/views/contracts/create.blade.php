@extends('layouts.operator')

@section('title', 'Tạo hợp đồng')
@section('page_title', 'Tạo hợp đồng')

@section('content')
    <x-ui.page-header
        title="Tạo hợp đồng"
        description="Nhập thông tin hợp đồng mới."
    >
        <x-ui.button-link :href="route('operator.contracts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin hợp đồng">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.contracts.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('project_id')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="code">Mã hợp đồng <span class="text-rose-600">*</span></label>
                    <input id="code" name="code" type="text" class="operator-input" value="{{ old('code') }}" maxlength="100" required placeholder="VD: CTR-001">
                    @error('code')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="total_value">Giá trị</label>
                    <input id="total_value" name="total_value" type="text" inputmode="decimal" data-money class="operator-input" value="{{ old('total_value') }}" placeholder="vd: 2.500.000.000">
                    @error('total_value')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="currency">Tiền tệ</label>
                    <select id="currency" name="currency" class="operator-select">
                        <option value="VND" @selected(old('currency', 'VND') === 'VND')>VND</option>
                        <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        <option value="EUR" @selected(old('currency') === 'EUR')>EUR</option>
                    </select>
                </div>

                <div class="operator-field">
                    <label for="signed_at">Ngày ký</label>
                    <input id="signed_at" name="signed_at" type="date" class="operator-input" value="{{ old('signed_at') }}">
                </div>

                <div class="operator-field">
                    <label for="start_date">Ngày bắt đầu</label>
                    <input id="start_date" name="start_date" type="date" class="operator-input" value="{{ old('start_date') }}">
                </div>

                <div class="operator-field">
                    <label for="end_date">Ngày kết thúc</label>
                    <input id="end_date" name="end_date" type="date" class="operator-input" value="{{ old('end_date') }}">
                    @error('end_date')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="operator-field">
                <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                <input id="title" name="title" type="text" class="operator-input" value="{{ old('title') }}" maxlength="255" required>
                @error('title')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo hợp đồng</button>
        </form>
    </x-ui.card>
@endsection
