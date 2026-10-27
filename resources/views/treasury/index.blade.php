@extends('layouts.operator')

@section('title', 'Ngân quỹ')
@section('page_title', 'Ngân quỹ dự án')

@section('content')
    <x-ui.page-header
        title="Ngân quỹ dự án"
        description="Tổng quan tiền của từng dự án: đang giữ, chi phí, chưa đối soát, tạm ứng còn nợ và cảnh báo."
    >
        @can('treasury.manage-parties')
            <x-ui.button-link :href="route('operator.treasury.parties.index')" variant="secondary">Đối tác tài chính</x-ui.button-link>
        @endcan
    </x-ui.page-header>

    <x-ui.card>
        @if ($projects->isEmpty())
            <x-ui.empty-state
                title="Chưa có dự án nào"
                description="Bạn chỉ thấy ngân quỹ của các dự án mình là thành viên."
            />
        @else
            @php $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫'; @endphp
            <x-ui.data-table :headers="['Mã', 'Dự án', 'Đang giữ', 'Chi phí', 'Tiền nhận chưa đối soát', 'Tạm ứng còn nợ', 'Cảnh báo', 'Thao tác']">
                @foreach ($projects as $project)
                    @php $row = $overview[(string) $project->id] ?? null; @endphp
                    <tr data-testid="treasury-project-row">
                        <td class="font-semibold text-slate-900">{{ $project->code }}</td>
                        <td class="font-medium text-slate-900">{{ $project->name }}</td>
                        <td class="text-sm font-semibold">{{ $money($row['held_total'] ?? 0) }}</td>
                        <td class="text-sm">{{ $money($row['expenses'] ?? 0) }}</td>
                        <td class="text-sm">{{ $money($row['unreconciled_funding'] ?? 0) }}</td>
                        <td class="text-sm">{{ $money($row['outstanding_advances'] ?? 0) }}</td>
                        <td class="text-sm" data-testid="treasury-project-alerts">@if (($row['alert_count'] ?? 0) > 0)<span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $row['alert_count'] }}</span>@else — @endif</td>
                        <td>
                            <a href="{{ route('operator.treasury.projects.show', $project->id) }}" class="operator-button operator-button-inline">Mở ngân quỹ</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($projects->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $projects->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
