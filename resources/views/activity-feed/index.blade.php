@extends('layouts.operator')

@section('title', 'Nhật ký hoạt động')
@section('page_title', 'Nhật ký hoạt động')

@section('content')
    <x-ui.page-header
        title="Nhật ký hoạt động"
        description="Dòng sự kiện hệ thống theo dự án — phục vụ truy vết và kiểm toán."
    />

    <x-ui.card>
        <form method="GET" action="{{ route('operator.activity-feed.index') }}" class="operator-form-grid mb-4">
            <div class="operator-field">
                <label for="project_id">Dự án</label>
                <select id="project_id" name="project_id" class="operator-select">
                    <option value="">Tất cả dự án</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(request('project_id') === (string) $project->id)>
                            {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="operator-field">
                <label for="event_key">Loại sự kiện</label>
                <input id="event_key" name="event_key" type="text" class="operator-input" value="{{ request('event_key') }}" placeholder="vd: material, receipt...">
            </div>
            <div class="operator-field">
                <label for="date_from">Từ ngày</label>
                <input id="date_from" name="date_from" type="date" class="operator-input" value="{{ request('date_from') }}">
            </div>
            <div class="operator-field">
                <label for="date_to">Đến ngày</label>
                <input id="date_to" name="date_to" type="date" class="operator-input" value="{{ request('date_to') }}">
            </div>
            <div class="operator-field">
                <label>&nbsp;</label>
                <button type="submit" class="operator-button operator-button-primary">Lọc</button>
            </div>
        </form>
    </x-ui.card>

    @if ($events->isEmpty())
        <x-ui.empty-state
            title="Chưa có sự kiện"
            description="Các thao tác nghiệp vụ sẽ xuất hiện tại đây khi hệ thống ghi nhận sự kiện."
        />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Thời gian', 'Sự kiện', 'Đối tượng', 'Dự án', 'Người thao tác']">
                @foreach ($events as $event)
                    <tr>
                        <td class="text-sm text-slate-600 whitespace-nowrap">{{ optional($event->occurred_at)->format('d/m/Y H:i:s') }}</td>
                        <td class="font-medium text-slate-900">{{ $event->event_key }}</td>
                        <td class="text-sm text-slate-600">
                            <div>{{ $event->aggregate_type }}</div>
                            <div class="text-xs text-slate-400">{{ $event->aggregate_id }}</div>
                        </td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $event->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $event->project?->code ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">{{ $event->actor?->name ?? 'Hệ thống' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            <div class="mt-4">
                {{ $events->links() }}
            </div>
        </x-ui.card>
    @endif
@endsection
