@extends('layouts.operator')

@section('title', 'Công việc')
@section('page_title', 'Công việc')

@section('content')
    <x-ui.page-header
        title="Công việc"
        description="Toàn bộ công việc trong tenant, sắp theo cập nhật gần nhất."
    >
        <x-ui.button-link :href="route('app.tasks.create')">Tạo công việc</x-ui.button-link>
    </x-ui.page-header>

    @if ($tasks->isEmpty())
        <x-ui.empty-state
            title="Chưa có công việc"
            description="Tạo công việc đầu tiên hoặc dùng trang Tiến độ dự án để lập kế hoạch."
        >
            <x-ui.button-link :href="route('operator.schedule.index')" variant="secondary">Mở Gantt</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Công việc', 'Dự án', 'Trạng thái', 'Ưu tiên', 'Tiến độ', 'Kết thúc']">
                @foreach ($tasks as $task)
                    <tr>
                        <td>
                            <a href="{{ route('app.tasks.show', $task->id) }}" class="operator-link font-medium">{{ $task->name ?? $task->title }}</a>
                        </td>
                        <td class="text-sm text-slate-600">{{ $task->project?->name ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$task->status" /></td>
                        <td class="text-sm text-slate-600">{{ $task->priority ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ (int) $task->progress_percent }}%</td>
                        <td class="text-sm text-slate-600">{{ $task->end_date ? \Illuminate\Support\Carbon::parse($task->end_date)->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
