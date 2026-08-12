@extends('layouts.operator')

@section('title', 'Duyệt tài liệu')
@section('page_title', 'Duyệt tài liệu')

@section('content')
    <x-ui.page-header
        title="Duyệt tài liệu"
        description="Danh sách tài liệu theo trạng thái phê duyệt."
    >
        <x-ui.button-link href="/app/documents" variant="secondary">Tất cả tài liệu</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="/app/documents/approvals" class="operator-form-grid mb-4">
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
        <x-ui.empty-state title="Không có tài liệu" description="Không có tài liệu nào khớp bộ lọc." />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Tài liệu', 'Dự án', 'Trạng thái', 'Người tải', 'Ngày tạo', 'Người xử lý', 'Hành động']">
                @foreach ($documents as $document)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $document->title ?? $document->name }}</td>
                        <td class="text-sm text-slate-600">{{ $document->project?->name ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$document->status ?? 'pending'" /></td>
                        <td class="text-sm text-slate-600">{{ $document->uploader?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($document->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="text-sm text-slate-600">
                            @if (in_array($document->status, ['approved', 'rejected'], true))
                                {{ $decisionUsers[$document->decision_by_id] ?? '—' }}
                                @if ($document->decision_at)
                                    <div class="text-xs text-slate-400">{{ $document->decision_at->format('d/m/Y H:i') }}</div>
                                @endif
                                @if ($document->decision_note)
                                    <div class="text-xs text-slate-500">{{ $document->decision_note }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($document->status === 'submitted' && $document->getRawOriginal('lifecycle_status') !== null && $document->getRawOriginal('approval_status') !== null)
                                <form method="POST" action="{{ route('app.documents.workflow.approve', ['document' => $document->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-primary">Duyệt</button>
                                </form>
                                <form method="POST" action="{{ route('app.documents.workflow.reject', ['document' => $document->id]) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="decision_note" value="Từ chối qua danh sách duyệt" />
                                    <button type="submit" class="operator-button operator-button-secondary">Từ chối</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            <div class="mt-4">
                {{ $documents->links() }}
            </div>
        </x-ui.card>
    @endif
@endsection
