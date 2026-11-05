@extends('layouts.operator')

@section('title', $project->name . ' — Lịch sử báo cáo OPPM')
@section('page_title', 'Lịch sử báo cáo OPPM')

@section('content')
    {{-- GAP-110 K-1: closed OPPM period reports (append-only). --}}
    <x-ui.page-header :title="'Lịch sử báo cáo OPPM — ' . $project->name" description="Các kỳ đã chốt; mỗi bản chốt giữ nguyên số liệu lúc chốt.">
        <x-ui.button-link :href="route('app.projects.oppm', $project->id)" variant="secondary">OPPM hiện tại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        @if ($snapshots->isEmpty())
            <x-ui.empty-state title="Chưa có bản chốt" description="PM hoặc người có quyền sửa dự án bấm “Chốt báo cáo” trên trang OPPM." />
        @else
            <x-ui.data-table :headers="['Kỳ', 'Chốt lúc', 'Mã kiểm tra', '']">
                @foreach ($snapshots as $row)
                    <tr data-testid="oppm-snapshot-row">
                        <td class="font-medium text-slate-900">{{ $row->period_label }}</td>
                        <td class="text-sm">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ substr($row->payload_hash, 0, 12) }}…</td>
                        <td class="flex gap-2">
                            <a href="{{ route('app.projects.oppm.snapshots.show', [$project->id, $row->id]) }}" class="operator-button operator-button-inline">Xem</a>
                            <a href="{{ route('app.projects.oppm.snapshots.compare', [$project->id, $row->id]) }}" class="operator-button operator-button-inline">So với kỳ trước</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
