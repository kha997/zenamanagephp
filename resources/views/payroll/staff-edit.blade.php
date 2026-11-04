@extends('layouts.operator')

@section('title', 'Sửa nhân sự')
@section('page_title', 'Sửa nhân sự')

@section('content')
    {{-- GAP-109 Q-1: edit one staff member, payroll.manage only. --}}
    <x-ui.page-header :title="'Sửa nhân sự — ' . $member->code" description="Đổi thông tin, giờ chuẩn riêng, thưởng dự kiến hoặc ngày nghỉ.">
        <x-ui.button-link :href="route('operator.payroll.staff.index')" variant="secondary">Danh sách nhân sự</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        @if ($errors->any())
            <ul class="mb-3 space-y-1 text-sm text-red-700" data-testid="payroll-errors">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('operator.payroll.staff.update', $member->id) }}" class="space-y-5 p-4" data-testid="payroll-staff-edit-form">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="code">Mã <span class="text-rose-600">*</span></label>
                    <input id="code" name="code" type="text" maxlength="50" required class="operator-input" value="{{ old('code', $member?->code) }}">
                </div>
                <div class="operator-field">
                    <label for="name">Họ tên <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" maxlength="255" required class="operator-input" value="{{ old('name', $member?->name) }}">
                </div>
                <div class="operator-field">
                    <label for="staff_type">Loại <span class="text-rose-600">*</span></label>
                    <select id="staff_type" name="staff_type" class="operator-input" required>
                        @foreach ($staffTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('staff_type', $member?->staff_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="is_overhead">Khối văn phòng (tính vào chi phí chung)</label>
                    <input type="hidden" name="is_overhead" value="0">
                    <input id="is_overhead" type="checkbox" name="is_overhead" value="1" @checked((bool) old('is_overhead', $member?->is_overhead))>
                </div>
                <div class="operator-field">
                    <label for="start_date">Ngày bắt đầu <span class="text-rose-600">*</span></label>
                    <input id="start_date" name="start_date" type="date" required class="operator-input" value="{{ old('start_date', $member?->start_date?->toDateString()) }}">
                </div>
                <div class="operator-field">
                    <label for="end_date">Ngày nghỉ</label>
                    <input id="end_date" name="end_date" type="date" class="operator-input" value="{{ old('end_date', $member?->end_date?->toDateString()) }}">
                </div>
                <div class="operator-field">
                    <label for="standard_hours_override">Giờ chuẩn riêng / tháng (bán thời gian)</label>
                    <input id="standard_hours_override" name="standard_hours_override" type="number" step="0.5" min="0" class="operator-input" value="{{ old('standard_hours_override', $member?->standard_hours_override) }}">
                </div>
                <div class="operator-field">
                    <label for="annual_bonus_estimate">Thưởng dự kiến cả năm (₫)</label>
                    <input id="annual_bonus_estimate" name="annual_bonus_estimate" type="number" step="1" min="0" class="operator-input" value="{{ old('annual_bonus_estimate', $member?->annual_bonus_estimate) }}">
                </div>
                <div class="operator-field">
                    <label for="user_id">Tài khoản đăng nhập</label>
                    <select id="user_id" name="user_id" class="operator-input">
                        <option value="">—</option>
                        @foreach ($users as $id => $label)
                            <option value="{{ $id }}" @selected(old('user_id', $member?->user_id) === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="financial_party_id">Đối tác Ngân quỹ</label>
                    <select id="financial_party_id" name="financial_party_id" class="operator-input">
                        <option value="">—</option>
                        @foreach ($parties as $id => $label)
                            <option value="{{ $id }}" @selected(old('financial_party_id', $member?->financial_party_id) === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Lưu</button>
        </form>
    </x-ui.card>
@endsection
