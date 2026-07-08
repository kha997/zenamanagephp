@extends('layouts.operator')

@section('title', 'Yêu cầu vật tư')
@section('page_title', 'Yêu cầu vật tư')

@section('content')
    <x-ui.page-header
        title="Yêu cầu vật tư"
        description="Tạo, gửi duyệt và phê duyệt yêu cầu vật tư."
    >
        <x-ui.button-link :href="route('operator.material-requests.create')">Tạo yêu cầu</x-ui.button-link>
    </x-ui.page-header>

    @if ($materialRequests->isEmpty())
        <x-ui.empty-state
            title="Chưa có yêu cầu vật tư"
            description="Tạo yêu cầu đầu tiên để bắt đầu luồng tiếp nhận vật tư."
        >
            <x-ui.button-link :href="route('operator.material-requests.create')">Tạo yêu cầu</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Mã yêu cầu', 'Dự án', 'Trạng thái', 'Ngày tạo', 'Thao tác']">
                @foreach ($materialRequests as $materialRequest)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $materialRequest->request_number }}</td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $materialRequest->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $materialRequest->project?->code ?? '' }}</div>
                        </td>
                        <td><x-ui.status-badge :status="$materialRequest->status" /></td>
                        <td class="text-sm text-slate-600">{{ optional($materialRequest->created_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($materialRequest->status === 'draft')
                                    <form method="POST" action="{{ route('operator.material-requests.submit', $materialRequest->id) }}">
                                        @csrf
                                        <button type="submit" class="operator-button operator-button-primary">Gửi duyệt</button>
                                    </form>
                                @endif

                                @if ($materialRequest->status === 'submitted')
                                    <form method="POST" action="{{ route('operator.material-requests.approve', $materialRequest->id) }}">
                                        @csrf
                                        <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
