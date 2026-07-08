@extends('layouts.operator')

@section('title', 'Tiến độ dự án')
@section('page_title', 'Tiến độ dự án')

@section('content')
    <x-ui.page-header
        title="Tiến độ dự án (Gantt)"
        description="Timeline công việc theo dự án — thanh tiến độ tô theo phần trăm hoàn thành."
    />

    <x-ui.card>
        <form method="GET" action="{{ route('operator.schedule.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="operator-field flex-1 min-w-64">
                <label for="project_id">Dự án</label>
                <select id="project_id" name="project_id" class="operator-select" onchange="this.form.submit()">
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === (string) $project->id)>
                            {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Xem</button>
        </form>
    </x-ui.card>

    @if ($projects->isEmpty())
        <x-ui.empty-state title="Chưa có dự án" description="Tạo dự án trước khi xem tiến độ." />
    @elseif ($timeline === null)
        <x-ui.empty-state
            title="Chưa có công việc có ngày bắt đầu/kết thúc"
            description="Gantt hiển thị các công việc có đủ ngày bắt đầu và kết thúc. Cập nhật ngày cho công việc để xem timeline."
        />
    @else
        <x-ui.card title="Timeline {{ $timeline['range_start'] }} → {{ $timeline['range_end'] }} ({{ $taskCount }} công việc)">
            {{-- Month markers --}}
            <div style="position:relative;height:1.5rem;margin-left:16rem;border-bottom:1px solid #e2e8f0;">
                @foreach ($timeline['months'] as $month)
                    <span style="position:absolute;left:{{ $month['offset_percent'] }}%;font-size:0.7rem;color:#64748b;border-left:1px solid #cbd5e1;padding-left:2px;">
                        {{ $month['label'] }}
                    </span>
                @endforeach
            </div>

            <div class="space-y-1" style="margin-top:0.5rem;">
                @foreach ($timeline['bars'] as $bar)
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <div style="width:15.5rem;flex-shrink:0;font-size:0.8rem;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $bar['label'] }}">
                            {{ $bar['label'] }}
                        </div>
                        <div style="position:relative;flex:1;height:1.6rem;background:#f8fafc;border-radius:0.375rem;">
                            <div title="{{ $bar['start'] }} → {{ $bar['end'] }} • {{ $bar['progress'] }}% • {{ $bar['status'] }}"
                                 style="position:absolute;left:{{ $bar['offset_percent'] }}%;width:{{ $bar['width_percent'] }}%;height:100%;border-radius:0.375rem;background:#99f6e4;overflow:hidden;">
                                <div style="height:100%;width:{{ $bar['progress'] }}%;background:#0f766e;"></div>
                            </div>
                            <span style="position:absolute;left:calc({{ $bar['offset_percent'] }}% + 4px);top:0;line-height:1.6rem;font-size:0.65rem;color:#134e4a;pointer-events:none;">
                                {{ $bar['progress'] }}%
                            </span>
                        </div>
                        <div style="width:5rem;flex-shrink:0;">
                            <x-ui.status-badge :status="$bar['status']" />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
@endsection
