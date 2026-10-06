@extends('layouts.operator')

@section('title', 'Đối tác tài chính')
@section('page_title', 'Đối tác tài chính')

@section('content')
    <x-ui.page-header
        title="Đối tác tài chính"
        description="Nhà đầu tư, bên trung gian, nhân viên giữ tiền, nhà cung cấp, tổ đội... dùng chung cho mọi dự án."
    >
        <x-ui.button-link :href="route('operator.treasury.index')" variant="secondary">Ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thêm đối tác">
        @include('treasury._errors')
        <form method="POST" action="{{ route('operator.treasury.parties.store') }}" class="space-y-5" data-testid="treasury-party-form">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="party_type">Loại <span class="text-rose-600">*</span></label>
                    <select id="party_type" name="party_type" class="operator-input" required>
                        @foreach ($partyTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('party_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="name">Tên <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" maxlength="255" required>
                </div>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Thêm đối tác</button>
        </form>
    </x-ui.card>

    <x-ui.card>
        @if ($parties->isEmpty())
            <x-ui.empty-state title="Chưa có đối tác" description="Thêm đối tác đầu tiên ở biểu mẫu phía trên." />
        @else
            <x-ui.data-table :headers="['Tên', 'Loại', 'Thao tác']">
                @foreach ($parties as $party)
                    <tr data-testid="treasury-party-row">
                        <td class="font-medium text-slate-900">{{ $party->name }}</td>
                        <td class="text-sm text-slate-600">{{ $partyTypes[$party->party_type] ?? $party->party_type }}</td>
                        <td class="flex gap-2">
                            <a href="{{ route('operator.treasury.parties.edit', $party->id) }}" class="operator-button operator-button-inline">Sửa</a>
                            <form method="POST" action="{{ route('operator.treasury.parties.destroy', $party->id) }}" onsubmit="return confirm('Xoá đối tác này?')">
                                @csrf
                                <button type="submit" class="operator-button operator-button-inline">Xoá</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            @if ($parties->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $parties->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
