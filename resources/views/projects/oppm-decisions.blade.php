@extends('layouts.operator')

@section('title', $project->name . ' — Quyết định cần xử lý')
@section('page_title', 'Quyết định cần xử lý')

@section('content')
    {{-- GAP-110 D-1: management decisions of one project; writes need the project PM or project.update (E-1). --}}
    @php
        $statuses = \App\Models\ProjectOppm\OppmDecision::STATUSES;
        $today = now()->toDateString();
    @endphp
    <x-ui.page-header :title="'Quyết định cần xử lý — ' . $project->name" description="Các quyết định Owner / PM cần đưa ra cho dự án, người quyết, hạn và kết quả.">
        <x-ui.button-link :href="route('app.projects.oppm', $project->id)" variant="secondary">Về OPPM</x-ui.button-link>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    @if ($canEdit)
        <x-ui.card title="Thêm quyết định">
            <form method="POST" action="{{ route('app.projects.oppm.decisions.store', $project->id) }}" class="grid gap-3 p-4 md:grid-cols-2" data-testid="oppm-decision-form">
                @csrf
                <label class="text-sm md:col-span-2">Nội dung cần quyết <span class="text-rose-600">*</span>
                    <input type="text" name="title" maxlength="255" required class="operator-input mt-1" value="{{ old('title') }}">
                </label>
                <label class="text-sm md:col-span-2">Bối cảnh
                    <textarea name="context" maxlength="2000" rows="2" class="operator-input mt-1">{{ old('context') }}</textarea>
                </label>
                <label class="text-sm">Người quyết
                    <select name="decision_maker_id" class="operator-input mt-1">
                        <option value="">—</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" @selected(old('decision_maker_id') === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">Hạn
                    <input type="date" name="due_date" class="operator-input mt-1" value="{{ old('due_date') }}">
                </label>
                <button type="submit" class="operator-button operator-button-primary md:col-span-2">Thêm</button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card>
        @if ($decisions->isEmpty())
            <x-ui.empty-state title="Chưa có quyết định" description="Thêm quyết định cần Owner / PM xử lý ở biểu mẫu phía trên." />
        @else
            <x-ui.data-table :headers="['Nội dung', 'Người quyết', 'Hạn', 'Trạng thái', 'Kết quả / lý do', '']">
                @foreach ($decisions as $row)
                    @php $overdue = $row->status === 'open' && $row->due_date !== null && $row->due_date->toDateString() < $today; @endphp
                    <tr data-testid="oppm-decision-row">
                        <td class="text-sm">
                            <span class="font-medium text-slate-900">{{ $row->title }}</span>
                            @if ($row->context)<div class="text-xs text-slate-500">{{ $row->context }}</div>@endif
                        </td>
                        <td class="text-sm">{{ $users[$row->decision_maker_id] ?? '—' }}</td>
                        <td class="text-sm">{{ $row->due_date?->format('d/m/Y') ?? '—' }}@if ($overdue) <span class="ml-1 rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>@endif</td>
                        <td class="text-sm">{{ $statuses[$row->status] ?? $row->status }}</td>
                        <td class="text-sm">{{ $row->outcome ?? '—' }}@if ($row->decided_at)<div class="text-xs text-slate-500">{{ $row->decided_at->format('d/m/Y') }} · {{ $users[$row->decided_by] ?? '—' }}</div>@endif</td>
                        <td class="text-sm">
                            @if ($canEdit && $row->status === 'open')
                                <details>
                                    <summary class="operator-link text-xs">Xử lý</summary>
                                    <form method="POST" action="{{ route('app.projects.oppm.decisions.decide', [$project->id, $row->id]) }}" class="mt-2 grid gap-1">
                                        @csrf
                                        <input type="text" name="outcome" maxlength="2000" required class="operator-input" placeholder="Kết quả quyết định">
                                        <button type="submit" class="operator-button operator-button-primary">Ghi đã quyết</button>
                                    </form>
                                    <form method="POST" action="{{ route('app.projects.oppm.decisions.update', [$project->id, $row->id]) }}" class="mt-2 grid gap-1">
                                        @csrf
                                        <input type="text" name="title" maxlength="255" required class="operator-input" value="{{ $row->title }}">
                                        <input type="hidden" name="context" value="{{ $row->context }}">
                                        <select name="decision_maker_id" class="operator-input">
                                            <option value="">—</option>
                                            @foreach ($users as $id => $name)
                                                <option value="{{ $id }}" @selected($row->decision_maker_id === $id)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="due_date" class="operator-input" value="{{ $row->due_date?->toDateString() }}">
                                        <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
                                    </form>
                                    <form method="POST" action="{{ route('app.projects.oppm.decisions.cancel', [$project->id, $row->id]) }}" class="mt-2 grid gap-1">
                                        @csrf
                                        <input type="text" name="reason" maxlength="2000" required class="operator-input" placeholder="Lý do huỷ">
                                        <button type="submit" class="operator-button operator-button-secondary">Huỷ quyết định</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
