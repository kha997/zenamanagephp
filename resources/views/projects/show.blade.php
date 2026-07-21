@extends('layouts.operator')

@section('title', $project->name . ' — Chi tiết dự án')
@section('page_title', 'Chi tiết dự án')

@section('content')
    <x-ui.page-header
        title="{{ $project->name }}"
        description="Project Details — {{ $project->code ?? '' }}"
    >
        <x-ui.button-link :href="route('app.projects')" variant="secondary">Quay lại</x-ui.button-link>
        <x-ui.button-link href="/app/projects/{{ $project->id }}/edit">Sửa dự án</x-ui.button-link>
        <x-ui.template-dropdown :links="$projectTemplates->map(fn ($tpl) => [
            'label' => $tpl->name,
            'href' => route('app.projects.documents.render', [$project->id, $tpl->id]),
        ])->all()" />
    </x-ui.page-header>

    <x-ui.card title="Thông tin chung">
        <div class="operator-form-grid">
            <x-ui.field-value label="Mã dự án" :value="$project->code ?? '—'" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$project->status" />
            </x-ui.field-value>
            <x-ui.field-value label="Tiến độ" :value="((int) $project->progress) . '%'" />
            <x-ui.field-value label="Quản lý dự án" :value="$project->manager?->name ?? '—'" />
            <x-ui.field-value label="Khách hàng" :value="$project->client?->name ?? '—'" />
            <x-ui.field-value label="Bắt đầu" :value="$project->start_date ? \Illuminate\Support\Carbon::parse($project->start_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Kết thúc" :value="$project->end_date ? \Illuminate\Support\Carbon::parse($project->end_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Ngân sách" :value="$project->budget_total ? number_format((float) $project->budget_total, 0, ',', '.') : '—'" />
        </div>

        @if ($project->description)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $project->description }}</p>
        @endif
    </x-ui.card>

    <x-ui.card title="Kế hoạch gốc">
        @if (session('success'))
            <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
        @endif

        @if ($delay['baseline'] !== null)
            <div class="operator-form-grid">
                <x-ui.field-value label="Bắt đầu (chốt)" :value="\Illuminate\Support\Carbon::parse($delay['baseline']->start_date)->format('d/m/Y')" />
                <x-ui.field-value label="Kết thúc (chốt)" :value="\Illuminate\Support\Carbon::parse($delay['baseline']->end_date)->format('d/m/Y')" />
                <x-ui.field-value label="Loại" :value="$delay['baseline']->type === 'contract' ? 'Hợp đồng' : 'Thực thi'" />
                <x-ui.field-value label="Phiên bản" :value="'v' . $delay['baseline']->version" />
                <x-ui.field-value label="Người chốt" :value="$delay['baseline']->creator?->name ?? '—'" />
                <x-ui.field-value label="Chốt lúc" :value="$delay['baseline']->created_at?->format('d/m/Y H:i') ?? '—'" />
            </div>
            @if ($delay['baseline']->note)
                <p class="mt-2 text-sm text-slate-600">{{ $delay['baseline']->note }}</p>
            @endif
            <div class="mt-3">@include('projects._delay-badge', ['delay' => $delay])</div>
        @else
            <p class="text-sm text-slate-500">Chưa chốt kế hoạch gốc — cờ trễ tiến độ chỉ hoạt động sau khi chốt.</p>
        @endif

        @if (auth()->user()?->hasPermission('project.update'))
            <form method="POST" action="{{ route('app.projects.baseline.store', $project->id) }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div class="operator-field w-40">
                    <label for="baseline_type">Loại kế hoạch</label>
                    <select id="baseline_type" name="type" class="operator-select">
                        <option value="execution">Thực thi</option>
                        <option value="contract">Hợp đồng</option>
                    </select>
                </div>
                <div class="operator-field flex-1 min-w-48">
                    <label for="baseline_note">Ghi chú / lý do chốt {{ $delay['baseline'] !== null ? 'lại' : '' }}</label>
                    <input id="baseline_note" name="note" type="text" class="operator-input" maxlength="1000">
                </div>
                <button type="submit" class="operator-button operator-button-secondary">Chốt kế hoạch từ ngày hiện tại</button>
            </form>
            @error('type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        @endif
    </x-ui.card>

    @include('projects._apply-work-template', ['project' => $project])

    <x-ui.card title="Công việc ({{ $sectionTasks->count() }})">
        <div class="mb-3">
            <x-ui.button-link href="/app/tasks/create?project_id={{ $project->id }}">Thêm công việc</x-ui.button-link>
        </div>

        @if ($sectionTasks->isEmpty())
            <p class="text-sm text-slate-500">Dự án chưa có công việc nào.</p>
        @else
            <x-ui.data-table :headers="['Công việc', 'Trạng thái', 'Tiến độ', 'Kết thúc']">
                @foreach ($sectionTasks as $task)
                    <tr>
                        <td>
                            <a href="/app/tasks/{{ $task->id }}" class="operator-link font-medium">{{ $task->name ?? $task->title }}</a>
                        </td>
                        <td><x-ui.status-badge :status="$task->status" /></td>
                        <td class="text-sm text-slate-600">{{ (int) $task->progress_percent }}%</td>
                        <td class="text-sm text-slate-600">{{ $task->end_date ? \Illuminate\Support\Carbon::parse($task->end_date)->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($documentChecklist !== null)
        <x-ui.card title="Checklist tài liệu">
            @if (empty($documentChecklist))
                <p class="text-sm text-slate-500">Không có yêu cầu tài liệu nào cho dự án này.</p>
            @else
                <x-ui.data-table :headers="['Bước', 'Yêu cầu', 'Còn thiếu']">
                    @foreach ($documentChecklist as $row)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $row['step_name'] }}</td>
                            <td class="text-sm text-slate-600">{{ implode(', ', $row['required']) }}</td>
                            <td class="text-sm">
                                @if (empty($row['missing']))
                                    <span class="text-emerald-600">Đủ</span>
                                @else
                                    <span class="text-amber-600">{{ implode(', ', $row['missing']) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>
    @endif

    @include('projects._design-progress', ['designItems' => $designItems, 'blockedItems' => $blockedItems, 'tasks' => $sectionTasks])

    <x-ui.card title="Hợp đồng & tài chính">
        @forelse ($contracts as $contract)
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                <a href="{{ route('operator.contracts.show', $contract->id) }}" class="font-medium">{{ $contract->code }}</a>
                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $contract->typeLabel() }}</span>
                <x-ui.status-badge :status="$contract->status" />
                <span>Giá trị: {{ number_format((float) $contract->total_value) }}</span>
                <span class="text-emerald-700">Đã thu: {{ number_format((float) ($contract->paid_total ?? 0)) }}</span>
                <span class="text-red-700">Đã chi: {{ number_format((float) ($contract->expense_total ?? 0)) }}</span>
                <span class="text-slate-500">Còn phải thu: {{ number_format((float) $contract->total_value - (float) ($contract->paid_total ?? 0)) }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Chưa có hợp đồng.</p>
        @endforelse
        @if ($contracts->isNotEmpty())
            <div class="mt-2 border-t border-slate-200 pt-2 text-sm font-medium">
                Tổng: {{ number_format((float) $contracts->sum('total_value')) }}
                · Đã thu {{ number_format((float) $contracts->sum('paid_total')) }}
                · Đã chi {{ number_format((float) $contracts->sum('expense_total')) }}
            </div>
        @endif
    </x-ui.card>

    <div class="flex flex-wrap gap-3">
        <x-ui.button-link :href="route('operator.schedule.index', ['project_id' => $project->id])" variant="secondary">Gantt tiến độ</x-ui.button-link>
    </div>
@endsection
