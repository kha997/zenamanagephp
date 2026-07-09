@extends('layouts.operator')

@section('title', 'Tài liệu')
@section('page_title', 'Tài liệu')

@section('content')
    <x-ui.page-header
        title="Tài liệu"
        description="Tài liệu dự án trong tenant — lọc theo dự án hoặc trạng thái."
    >
        <x-ui.button-link href="/app/documents/create">Tải lên tài liệu</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="/app/documents" class="operator-form-grid mb-4">
            <div class="operator-field">
                <label for="project_id">Dự án</label>
                <select id="project_id" name="project_id" class="operator-select">
                    <option value="">Tất cả dự án</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(request('project_id') === (string) $project->id)>
                            {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="operator-field">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                    <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                </select>
            </div>
            <div class="operator-field">
                <label>&nbsp;</label>
                <button type="submit" class="operator-button operator-button-primary">Lọc</button>
            </div>
        </form>
    </x-ui.card>

    @if ($documents->isEmpty())
        <x-ui.empty-state
            title="Chưa có tài liệu"
            description="Tải lên tài liệu đầu tiên cho dự án của bạn."
        />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Tài liệu', 'Dự án', 'Trạng thái', 'Người tải', 'Ngày tạo']">
                @foreach ($documents as $document)
                    <tr>
                        <td>
                            <div class="font-medium text-slate-900">{{ $document->title ?? $document->name }}</div>
                            <div class="text-sm text-slate-500">{{ $document->file_name ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">{{ $document->project?->name ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$document->status ?? 'pending'" /></td>
                        <td class="text-sm text-slate-600">{{ $document->uploader?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($document->created_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            <div class="mt-4">
                {{ $documents->links() }}
            </div>
        </x-ui.card>
    @endif
@endsection
