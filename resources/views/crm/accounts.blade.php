@extends('layouts.operator')

@section('title', 'CRM — Khách hàng')
@section('page_title', 'CRM — Khách hàng')

@section('content')
    <x-ui.page-header
        title="Khách hàng"
        description="Khách hàng cá nhân và công ty — mỗi khách có thể có nhiều cơ hội."
    >
        <x-ui.button-link :href="route('operator.crm.index')" variant="secondary">Pipeline</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thêm khách hàng">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.crm.accounts.store') }}" class="operator-form-grid">
            @csrf
            <div class="operator-field">
                <label for="display_name">Tên khách hàng <span class="text-rose-600">*</span></label>
                <input id="display_name" name="display_name" type="text" class="operator-input" value="{{ old('display_name') }}" required>
            </div>
            <div class="operator-field">
                <label for="account_type">Loại</label>
                <select id="account_type" name="account_type" class="operator-select">
                    <option value="individual" @selected(old('account_type', 'individual') === 'individual')>Cá nhân</option>
                    <option value="company" @selected(old('account_type') === 'company')>Công ty</option>
                </select>
            </div>
            <div class="operator-field">
                <label for="phone">Điện thoại</label>
                <input id="phone" name="phone" type="text" class="operator-input" value="{{ old('phone') }}">
            </div>
            <div class="operator-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="operator-input" value="{{ old('email') }}">
            </div>
            <div class="operator-field">
                <label for="province_or_city">Tỉnh/Thành</label>
                <input id="province_or_city" name="province_or_city" type="text" class="operator-input" value="{{ old('province_or_city') }}">
            </div>
            <div class="operator-field">
                <label>&nbsp;</label>
                <button type="submit" class="operator-button operator-button-primary">Thêm</button>
            </div>
        </form>
    </x-ui.card>

    @if ($accounts->isEmpty())
        <x-ui.empty-state title="Chưa có khách hàng" description="Thêm khách hàng đầu tiên hoặc chuyển từ hộp lead." />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Khách hàng', 'Loại', 'Liên hệ', 'Tỉnh/Thành', 'Cơ hội', 'Trạng thái']">
                @foreach ($accounts as $account)
                    <tr>
                        <td>
                            <div class="font-medium text-slate-900">{{ $account->display_name }}</div>
                            <div class="text-xs text-slate-500">{{ $account->account_code }}</div>
                        </td>
                        <td class="text-sm text-slate-600">{{ $account->account_type === 'company' ? 'Công ty' : 'Cá nhân' }}</td>
                        <td class="text-sm text-slate-600">{{ $account->phone ?? $account->email ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $account->province_or_city ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $account->opportunities_count }}</td>
                        <td><x-ui.status-badge :status="$account->status" /></td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
