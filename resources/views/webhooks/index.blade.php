@extends('layouts.operator')

@section('title', 'Webhooks')
@section('page_title', 'Webhooks')

@section('content')
    <x-ui.page-header
        title="Webhooks"
        description="Đẩy sự kiện hệ thống (EventRecord) sang hệ thống ngoài theo thời gian thực, ký HMAC SHA-256."
    />

    <x-ui.card title="Tạo webhook mới">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.webhooks.store') }}" class="space-y-5">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="name">Tên</label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" required placeholder="vd: ERP sync">
                </div>
                <div class="operator-field">
                    <label for="url">URL nhận (HTTPS)</label>
                    <input id="url" name="url" type="url" class="operator-input" value="{{ old('url') }}" required placeholder="https://example.com/hooks/zena">
                </div>
                <div class="operator-field">
                    <label for="event_keys">Tiền tố sự kiện (phân cách bằng dấu phẩy, bỏ trống = tất cả)</label>
                    <input id="event_keys" name="event_keys" type="text" class="operator-input" value="{{ old('event_keys') }}" placeholder="vd: material_request., task.">
                </div>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tạo webhook</button>
        </form>
    </x-ui.card>

    @if ($endpoints->isEmpty())
        <x-ui.empty-state title="Chưa có webhook" description="Tạo webhook đầu tiên để tích hợp hệ thống ngoài." />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Tên', 'URL', 'Sự kiện', 'Trạng thái', 'Giao lần cuối', 'Lỗi liên tiếp', 'Thao tác']">
                @foreach ($endpoints as $endpoint)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $endpoint->name }}</td>
                        <td class="text-sm text-slate-600 max-w-xs truncate">{{ $endpoint->url }}</td>
                        <td class="text-sm text-slate-600">{{ implode(', ', (array) $endpoint->event_keys) }}</td>
                        <td><x-ui.status-badge :status="$endpoint->is_active ? 'active' : 'inactive'" /></td>
                        <td class="text-sm text-slate-600">{{ optional($endpoint->last_delivered_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $endpoint->failure_count }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-3">
                                <form method="POST" action="{{ route('operator.webhooks.toggle', $endpoint->id) }}">
                                    @csrf
                                    <button type="submit" class="operator-button">{{ $endpoint->is_active ? 'Tắt' : 'Bật' }}</button>
                                </form>
                                <form method="POST" action="{{ route('operator.webhooks.destroy', $endpoint->id) }}"
                                      onsubmit="return confirm('Xóa webhook này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="operator-button">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
