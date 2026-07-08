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

    <div class="flex flex-wrap gap-3">
        <x-ui.button-link href="/app/tasks/{{ $task->id }}/documents" variant="secondary">Tài liệu</x-ui.button-link>
        <x-ui.button-link href="/app/tasks/{{ $task->id }}/history" variant="secondary">Lịch sử</x-ui.button-link>
    </div>
@endsection
