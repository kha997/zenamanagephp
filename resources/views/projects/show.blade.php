@extends('layouts.operator')

@section('title', $project->name . ' — Chi tiết dự án')
@section('page_title', 'Chi tiết dự án')

@section('content')
    <x-ui.page-header
        title="{{ $project->name }}"
        description="Project Details — {{ $project->code ?? '' }}"
    >
        <x-ui.button-link :href="route('app.projects')" variant="secondary">Quay lại</x-ui.button-link>
        <x-ui.button-link href="/app/projects/{{ $project->id }}/edit">Sửa dự án</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin chung">
        <div class="operator-form-grid">
            <x-ui.field-value label="Mã dự án" :value="$project->code ?? '—'" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$project->status" />
            </x-ui.field-value>
            <x-ui.field-value label="Tiến độ" :value="((int) $project->progress) . '%'" />
            <x-ui.field-value label="Quản lý dự án" :value="$project->manager?->name ?? '—'" />
            <x-ui.field-value label="Khách hàng" :value="$project->client?->name ?? '—'" />
            <x-ui.field-value label="Bắt đầu" :value="$project->start_date ? \Illuminate\Support\Carbon::parse($project->start_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Kết thúc" :value="$project->end_date ? \Illuminate\Support\Carbon::parse($project->end_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Ngân sách" :value="$project->budget_total ? number_format((float) $project->budget_total, 0, ',', '.') : '—'" />
        </div>

        @if ($project->description)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $project->description }}</p>
        @endif
    </x-ui.card>

    <x-ui.card title="Công việc ({{ $project->tasks->count() }})">
        <div class="mb-3">
            <x-ui.button-link href="/app/tasks/create?project_id={{ $project->id }}">Thêm công việc</x-ui.button-link>
        </div>

        @if ($project->tasks->isEmpty())
            <p class="text-sm text-slate-500">Dự án chưa có công việc nào.</p>
        @else
            <x-ui.data-table :headers="['Công việc', 'Trạng thái', 'Tiến độ', 'Kết thúc']">
                @foreach ($project->tasks as $task)
                    <tr>
                        <td>
                            <a href="/app/tasks/{{ $task->id }}" class="operator-link font-medium">{{ $task->name ?? $task->title }}</a>
                        </td>
                        <td><x-ui.status-badge :status="$task->status" /></td>
                        <td class="text-sm text-slate-600">{{ (int) $task->progress_percent }}%</td>
                        <td class="text-sm text-slate-600">{{ $task->end_date ? \Illuminate\Support\Carbon::parse($task->end_date)->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($documentChecklist !== null)
        <x-ui.card title="Checklist tài liệu">
            @if (empty($documentChecklist))
                <p class="text-sm text-slate-500">Không có yêu cầu tài liệu nào cho dự án này.</p>
            @else
                <x-ui.data-table :headers="['Bước', 'Yêu cầu', 'Còn thiếu']">
                    @foreach ($documentChecklist as $row)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $row['step_name'] }}</td>
                            <td class="text-sm text-slate-600">{{ implode(', ', $row['required']) }}</td>
                            <td class="text-sm">
                                @if (empty($row['missing']))
                                    <span class="text-emerald-600">Đủ</span>
                                @else
                                    <span class="text-amber-600">{{ implode(', ', $row['missing']) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>
    @endif

    <div class="flex flex-wrap gap-3">
        <x-ui.button-link href="/app/projects/{{ $project->id }}/documents" variant="secondary">Tài liệu dự án</x-ui.button-link>
        <x-ui.button-link href="/app/projects/{{ $project->id }}/history" variant="secondary">Lịch sử</x-ui.button-link>
        <x-ui.button-link :href="route('operator.schedule.index', ['project_id' => $project->id])" variant="secondary">Gantt tiến độ</x-ui.button-link>
    </div>
@endsection
