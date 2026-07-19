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

    <form method="GET" action="{{ route('app.tasks') }}" class="mb-4 flex items-end gap-2">
        <div class="operator-field w-64">
            <label for="assigned_to">Người phụ trách</label>
            <select id="assigned_to" name="assigned_to" class="operator-select">
                <option value="">Tất cả</option>
                @foreach ($tenantUsers as $tenantUser)
                    <option value="{{ $tenantUser->id }}" @selected($assignedTo === (string) $tenantUser->id)>{{ $tenantUser->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
    </form>

    @if ($tasks->isEmpty())
        <x-ui.empty-state
            title="Chưa có công việc"
            description="Tạo công việc đầu tiên hoặc dùng trang Tiến độ dự án để lập kế hoạch."
        >
            <x-ui.button-link :href="route('operator.schedule.index')" variant="secondary">Mở Gantt</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Công việc', 'Dự án', 'Người phụ trách', 'Trạng thái', 'Ưu tiên', 'Tiến độ', 'Kết thúc']">
                @foreach ($tasks as $task)
                    <tr>
                        <td>
                            <a href="{{ route('app.tasks.show', $task->id) }}" class="operator-link font-medium">{{ $task->name ?? $task->title }}</a>
                        </td>
                        <td class="text-sm text-slate-600">{{ $task->project?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $task->assignee?->name ?? '—' }}</td>
                        <td>
                            <x-ui.status-badge :status="$task->status" />
                            @if ($task->blocked_at)
                                <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800 ml-1">Vướng</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">{{ $task->priority ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ (int) $task->progress_percent }}%</td>
                        <td class="text-sm text-slate-600">{{ $task->end_date ? \Illuminate\Support\Carbon::parse($task->end_date)->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
