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

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($canChangeEvidencePolicy)
        {{-- GAP-106 M-1: tenant switch, owner-level only. --}}
        <x-ui.card title="Quy định chứng từ">
            <form method="POST" action="{{ route('operator.treasury.settings.evidence') }}" class="flex flex-wrap items-center gap-3 p-4" data-testid="treasury-evidence-policy-form">
                @csrf
                <input type="hidden" name="evidence_required" value="0">
                <label class="text-sm"><input type="checkbox" name="evidence_required" value="1" @checked($evidenceRequired)> Khoản chi phải có ít nhất một chứng từ mới gửi duyệt được</label>
                <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
            </form>
        </x-ui.card>
        {{-- GAP-108 N-1 / S-1: tenant thresholds, owner-level only. --}}
        <x-ui.card title="Ngưỡng cảnh báo">
            <form method="POST" action="{{ route('operator.treasury.settings.thresholds') }}" class="flex flex-wrap items-end gap-4 p-4" data-testid="treasury-thresholds-form">
                @csrf
                <label class="text-sm">Tiền nhận chưa đối soát quá (ngày)
                    <input type="number" name="unreconciled_funding_days" min="1" max="365" required class="operator-input mt-1 w-28" value="{{ old('unreconciled_funding_days', $thresholds['unreconciled_funding_days']) }}">
                </label>
                <label class="text-sm">Tạm ứng không có hạn quá hạn sau (ngày)
                    <input type="number" name="advance_default_term_days" min="1" max="365" required class="operator-input mt-1 w-28" value="{{ old('advance_default_term_days', $thresholds['advance_default_term_days']) }}">
                </label>
                <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
                @foreach (['unreconciled_funding_days', 'advance_default_term_days'] as $field)
                    @error($field)<p class="w-full text-sm text-red-700">{{ $message }}</p>@enderror
                @endforeach
            </form>
        </x-ui.card>
    @endif

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
