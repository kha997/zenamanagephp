@extends('layouts.operator')

@section('title', 'API Tokens')
@section('page_title', 'API Tokens')

@section('content')
    <x-ui.page-header
        title="API Tokens"
        description="Token cá nhân (Sanctum) để tích hợp API. Token chỉ hiển thị một lần khi tạo."
    />

    <x-ui.card title="Tạo token mới">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.api-tokens.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="operator-field flex-1 min-w-64">
                <label for="name">Tên token</label>
                <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" required placeholder="vd: ci-pipeline, mobile-app">
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tạo token</button>
        </form>
    </x-ui.card>

    @if ($tokens->isEmpty())
        <x-ui.empty-state title="Chưa có token" description="Tạo token đầu tiên để gọi API từ hệ thống ngoài." />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Tên', 'Dùng lần cuối', 'Ngày tạo', 'Thao tác']">
                @foreach ($tokens as $token)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $token->name }}</td>
                        <td class="text-sm text-slate-600">{{ optional($token->last_used_at)->format('d/m/Y H:i') ?? 'Chưa dùng' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($token->created_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('operator.api-tokens.destroy', $token->id) }}"
                                  onsubmit="return confirm('Thu hồi token này? Các tích hợp đang dùng sẽ mất truy cập.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="operator-button">Thu hồi</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
