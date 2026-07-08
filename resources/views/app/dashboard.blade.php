@extends('layouts.operator')

@section('title', 'Bảng điều hành dự án')
@section('page_title', 'Bảng điều hành dự án')

@section('content')
    <x-ui.page-header
        title="Bảng điều hành dự án"
        description="Tổng quan dự án, công việc và hoạt động gần đây trong tenant của bạn."
    />

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
        <x-ui.card>
            <div class="text-sm font-medium text-slate-500">Công việc đang mở</div>
            <div class="mt-1 text-3xl font-bold text-slate-900">{{ $dashboardStats['activeTasks'] }}</div>
            <a href="{{ route('app.tasks') }}" class="operator-link text-sm">Mở danh sách →</a>
        </x-ui.card>
        <x-ui.card>
            <div class="text-sm font-medium text-slate-500">Hoàn thành hôm nay</div>
            <div class="mt-1 text-3xl font-bold text-slate-900">{{ $dashboardStats['completedToday'] }}</div>
            <div class="text-sm text-slate-500">Tỷ lệ hoàn thành: {{ $dashboardStats['completionRate'] }}</div>
        </x-ui.card>
        <x-ui.card>
            <div class="text-sm font-medium text-slate-500">Thành viên</div>
            <div class="mt-1 text-3xl font-bold text-slate-900">{{ $dashboardStats['teamMembers'] }}</div>
            <a href="{{ route('app.team.index') }}" class="operator-link text-sm">Xem nhóm →</a>
        </x-ui.card>
        <x-ui.card>
            <div class="text-sm font-medium text-slate-500">Dự án</div>
            <div class="mt-1 text-3xl font-bold text-slate-900">{{ $dashboardStats['projects'] }}</div>
            <a href="{{ route('app.projects') }}" class="operator-link text-sm">Mở danh sách →</a>
        </x-ui.card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card title="Dự án cập nhật gần đây">
            @if ($recentProjects->isEmpty())
                <p class="text-sm text-slate-500">Chưa có dự án.</p>
            @else
                <x-ui.data-table :headers="['Dự án', 'Trạng thái', 'Tiến độ']">
                    @foreach ($recentProjects as $project)
                        <tr>
                            <td>
                                <a href="{{ route('app.projects.show', $project->id) }}" class="operator-link font-medium">{{ $project->name }}</a>
                                <div class="text-sm text-slate-500">{{ $project->code }}</div>
                            </td>
                            <td><x-ui.status-badge :status="$project->status" /></td>
                            <td class="text-sm text-slate-600">{{ (int) $project->progress }}%</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>

        <x-ui.card title="Hoạt động gần đây">
            @if ($recentEvents->isEmpty())
                <p class="text-sm text-slate-500">Chưa có sự kiện nào được ghi nhận.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($recentEvents as $event)
                        <li class="text-sm">
                            <span class="font-medium text-slate-900">{{ $event->event_key }}</span>
                            <span class="text-slate-500">— {{ $event->actor?->name ?? 'Hệ thống' }} · {{ optional($event->occurred_at)->format('d/m/Y H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">
                    <a href="{{ route('operator.activity-feed.index') }}" class="operator-link text-sm">Xem toàn bộ nhật ký hoạt động →</a>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
