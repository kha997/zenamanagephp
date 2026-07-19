@extends('layouts.operator')

@section('title', 'Dự án')
@section('page_title', 'Dự án')

@section('content')
    <x-ui.page-header
        title="Dự án"
        description="Danh sách dự án của tenant — bấm vào tên để xem chi tiết."
    >
        <x-ui.button-link :href="route('app.projects.create')">Tạo dự án</x-ui.button-link>
    </x-ui.page-header>

    @if ($projects->isEmpty())
        <x-ui.empty-state
            title="Chưa có dự án"
            description="Tạo dự án đầu tiên để bắt đầu quản lý công việc, mua sắm và kiểm định."
        >
            <x-ui.button-link :href="route('app.projects.create')">Tạo dự án</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Dự án', 'Trạng thái', 'Tiến độ', 'Tiến độ KH', 'Bắt đầu', 'Kết thúc', 'Ngân sách']">
                @foreach ($projects as $project)
                    <tr>
                        <td>
                            <a href="{{ route('app.projects.show', $project->id) }}" class="operator-link font-medium">{{ $project->name }}</a>
                            <div class="text-sm text-slate-500">{{ $project->code }}</div>
                        </td>
                        <td><x-ui.status-badge :status="$project->status" /></td>
                        <td class="text-sm text-slate-600">{{ (int) $project->progress }}%</td>
                        <td>@include('projects._delay-badge', ['delay' => $delays[(string) $project->id]])</td>
                        <td class="text-sm text-slate-600">{{ $project->start_date ? \Illuminate\Support\Carbon::parse($project->start_date)->format('d/m/Y') : '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $project->end_date ? \Illuminate\Support\Carbon::parse($project->end_date)->format('d/m/Y') : '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $project->budget_total ? number_format((float) $project->budget_total, 0, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
