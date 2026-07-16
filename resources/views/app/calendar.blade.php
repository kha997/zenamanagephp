@extends('layouts.operator')

@section('title', 'Lịch công việc')
@section('page_title', 'Lịch công việc')

@section('content')
    <x-ui.page-header
        title="Lịch công việc — 30 ngày tới"
        description="Công việc nhóm theo ngày kết thúc (deadline)."
    />

    @if (empty($tasksByDate) || $tasksByDate->isEmpty())
        <x-ui.empty-state
            title="Không có deadline trong 30 ngày tới"
            description="Các công việc có ngày kết thúc trong 30 ngày tới sẽ hiển thị tại đây."
        />
    @else
        <div class="space-y-4">
            @foreach ($tasksByDate as $date => $tasks)
                <x-ui.card :title="\Illuminate\Support\Carbon::parse($date)->format('l, d/m/Y')">
                    <ul class="space-y-2">
                        @foreach ($tasks as $task)
                            <li class="flex flex-wrap items-center gap-3 text-sm">
                                <x-ui.status-badge :status="$task->status" />
                                <a href="{{ route('app.tasks.show', $task->id) }}" class="operator-link font-medium">{{ $task->name ?? $task->title }}</a>
                                <span class="text-slate-500">{{ $task->project?->name ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    @endif
@endsection
