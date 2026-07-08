@extends('layouts.operator')

@section('title', 'Nhật ký công trường')
@section('page_title', 'Nhật ký công trường')

@section('content')
    <x-ui.page-header
        title="Nhật ký công trường"
        description="Ghi nhận công việc, nhân lực, thiết bị và an toàn hằng ngày tại công trường."
    >
        <x-ui.button-link :href="route('operator.site-diaries.create')">Tạo nhật ký</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.site-diaries.index') }}" class="operator-form-grid mb-4">
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
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="draft" @selected(request('status') === 'draft')>Bản nháp</option>
                    <option value="submitted" @selected(request('status') === 'submitted')>Chờ duyệt</option>
                    <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                </select>
            </div>
            <div class="operator-field">
                <label>&nbsp;</label>
                <button type="submit" class="operator-button operator-button-primary">Lọc</button>
            </div>
        </form>
    </x-ui.card>

    @if ($siteDiaries->isEmpty())
        <x-ui.empty-state
            title="Chưa có nhật ký công trường"
            description="Tạo nhật ký đầu tiên để bắt đầu theo dõi hoạt động hằng ngày."
        >
            <x-ui.button-link :href="route('operator.site-diaries.create')">Tạo nhật ký</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Ngày', 'Dự án', 'Công việc', 'Nhân lực', 'Trạng thái', 'Người lập', 'Thao tác']">
                @foreach ($siteDiaries as $siteDiary)
                    <tr>
                        <td class="font-semibold text-slate-900">
                            <a href="{{ route('operator.site-diaries.show', $siteDiary->id) }}" class="operator-link">
                                {{ optional($siteDiary->diary_date)->format('d/m/Y') }}
                            </a>
                        </td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $siteDiary->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $siteDiary->project?->code ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600 max-w-xs truncate">{{ Str::limit($siteDiary->work_performed, 80) }}</td>
                        <td class="text-sm text-slate-600">{{ $siteDiary->manpower_count }}</td>
                        <td><x-ui.status-badge :status="$siteDiary->status" /></td>
                        <td class="text-sm text-slate-600">{{ $siteDiary->creator?->name ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($siteDiary->status === 'draft')
                                    <form method="POST" action="{{ route('operator.site-diaries.submit', $siteDiary->id) }}">
                                        @csrf
                                        <button type="submit" class="operator-button operator-button-primary">Gửi duyệt</button>
                                    </form>
                                @endif

                                @if ($siteDiary->status === 'submitted')
                                    <form method="POST" action="{{ route('operator.site-diaries.approve', $siteDiary->id) }}">
                                        @csrf
                                        <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
