@extends('layouts.operator')

@section('title', 'Báo cáo ngân quỹ — ' . $project->name)
@section('page_title', 'Báo cáo ngân quỹ')

@section('content')
    <x-ui.page-header
        title="Báo cáo ngân quỹ — {{ $project->name }}"
        description="Số liệu lấy thẳng từ sổ ngân quỹ của dự án. Dòng tiền tính theo ngày giao dịch; chuyển ví nội bộ không tính."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.treasury.projects.reports.index', $project->id) }}" class="flex flex-wrap items-end gap-2 p-4" data-testid="treasury-report-picker">
            <div class="operator-field">
                <label for="report">Báo cáo</label>
                <select id="report" name="report" class="operator-input">
                    @foreach (\App\Services\Treasury\TreasuryReportService::REPORTS as $key => $title)
                        <option value="{{ $key }}" @selected($reportKey === $key)>{{ $title }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-inline">Xem</button>
            @if ($canExport)
                <a href="{{ route('operator.treasury.projects.reports.export', [$project->id, $reportKey]) }}" class="operator-button operator-button-secondary" data-testid="treasury-report-export">Xuất CSV</a>
            @endif
        </form>
    </x-ui.card>

    <x-ui.card :title="$report['title']">
        @if ($report['rows'] === [])
            <x-ui.empty-state title="Không có số liệu" description="Báo cáo này chưa có dòng nào cho dự án." />
        @else
            <x-ui.data-table :headers="$report['headers']">
                @foreach ($report['rows'] as $row)
                    <tr data-testid="treasury-report-row">
                        @foreach ($row as $cell)
                            <td class="text-sm">{{ is_string($cell) && preg_match('/^-?\d+\.\d{2}$/', $cell) === 1 ? number_format((float) $cell, 0, ',', '.') . ' ₫' : $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
