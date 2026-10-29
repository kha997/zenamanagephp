@extends('layouts.operator')

@section('title', 'Công việc theo mẫu')
@section('page_title', 'Công việc theo mẫu')

@section('content')
    <x-ui.page-header
        title="Công việc theo mẫu"
        :description="'Dự án: ' . $project->name"
    >
        <x-ui.button-link :href="route('app.projects.show', $project->id)" variant="secondary">Quay lại dự án</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        @if ($instances->isEmpty())
            <x-ui.empty-state
                title="Chưa có công việc theo mẫu"
                description="Áp dụng một mẫu công việc từ trang dự án để bắt đầu."
            />
        @else
            <x-ui.data-table :headers="['Mẫu', 'Phiên bản', 'Tiến độ', 'Trạng thái', 'Ngày tạo', 'Thao tác']">
                @foreach ($instances as $instance)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $instance->templateVersion?->template?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $instance->templateVersion?->semver ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $instance->finished_steps_count }}/{{ $instance->steps_count }}</td>
                        <td class="text-sm text-slate-600">{{ $instance->status }}</td>
                        <td class="text-sm text-slate-600">{{ optional($instance->created_at)->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('app.projects.work-instances.show', ['project' => $project->id, 'instance' => $instance->id]) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
