@extends('layouts.operator')

@section('title', ($task->name ?? $task->title) . ' — Chi tiết công việc')
@section('page_title', 'Chi tiết công việc')

@section('content')
    <x-ui.page-header
        title="{{ $task->name ?? $task->title }}"
        description="Thuộc dự án: {{ $task->project?->name ?? '—' }}"
    >
        <x-ui.button-link href="/app/tasks" variant="secondary">Quay lại</x-ui.button-link>
        <x-ui.button-link href="/app/tasks/{{ $task->id }}/edit">Sửa công việc</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin công việc">
        <div class="operator-form-grid">
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$task->status" />
            </x-ui.field-value>
            <x-ui.field-value label="Ưu tiên" :value="$task->priority ?? '—'" />
            <x-ui.field-value label="Tiến độ" :value="((int) $task->progress_percent) . '%'" />
            <x-ui.field-value label="Bắt đầu" :value="$task->start_date ? \Illuminate\Support\Carbon::parse($task->start_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Kết thúc" :value="$task->end_date ? \Illuminate\Support\Carbon::parse($task->end_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Dự án">
                @if ($task->project)
                    <a href="/app/projects/{{ $task->project->id }}" class="operator-link">{{ $task->project->name }}</a>
                @else
                    —
                @endif
            </x-ui.field-value>
        </div>

        @if ($task->description)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $task->description }}</p>
        @endif
    </x-ui.card>

    {{-- Khối vướng mắc --}}
    @if (auth()->user()?->hasPermission('task.update'))
        @if ($task->blocked_at)
            <div class="rounded border border-red-200 bg-red-50 p-4">
                <div class="mb-2 font-semibold text-red-700">Đang vướng</div>
                <p class="mb-2 text-sm text-red-800">{{ $task->blocker_note }}</p>
                <p class="mb-3 text-xs text-red-500">Ghi nhận lúc {{ optional($task->blocked_at)->format('d/m/Y H:i') }}</p>
                <form method="POST" action="{{ route('app.tasks.unblock', $task->id) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">Gỡ vướng</button>
                </form>
            </div>
        @else
            <x-ui.card title="Báo vướng">
                <form method="POST" action="{{ route('app.tasks.block', $task->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="operator-field flex-1 min-w-64">
                        <label for="blocker_note">Nội dung vướng mắc</label>
                        <textarea id="blocker_note" name="blocker_note" rows="2" maxlength="1000" class="operator-input" required placeholder="Mô tả vấn đề đang gặp...">{{ old('blocker_note') }}</textarea>
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Báo vướng</button>
                </form>
            </x-ui.card>
        @endif
    @elseif ($task->blocked_at)
        <div class="rounded border border-red-200 bg-red-50 p-4">
            <div class="mb-1 font-semibold text-red-700">Đang vướng</div>
            <p class="text-sm text-red-800">{{ $task->blocker_note }}</p>
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <x-ui.button-link href="/app/tasks/{{ $task->id }}/documents" variant="secondary">Tài liệu</x-ui.button-link>
        <x-ui.button-link href="/app/tasks/{{ $task->id }}/history" variant="secondary">Lịch sử</x-ui.button-link>
    </div>
@endsection
