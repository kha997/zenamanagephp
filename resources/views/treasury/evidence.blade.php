@extends('layouts.operator')

@section('title', 'Chứng từ — ' . $project->name)
@section('page_title', 'Chứng từ')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $types = \App\Services\Treasury\TreasuryEvidenceService::EVIDENCE_TYPES;
    @endphp

    <x-ui.page-header
        title="Chứng từ — {{ $typeLabel }} {{ $money($document->amount) }}"
        description="{{ $project->name }} · {{ $document->transaction_date?->format('d/m/Y') ?? '—' }}{{ $document->reference ? ' · ' . $document->reference : '' }}"
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    @include('treasury._errors')
    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif

    <x-ui.card title="File đính kèm ({{ count($attachments) }})">
        @if ($attachments === [])
            <p class="p-4 text-sm text-slate-500" data-testid="treasury-evidence-empty">Chưa có chứng từ.</p>
        @else
            <x-ui.data-table :headers="['Loại', 'Tên file', 'Ghi chú', 'Người tải', 'Ngày', '']">
                @foreach ($attachments as $attachment)
                    <tr data-testid="treasury-evidence-row">
                        <td class="text-sm">{{ $types[$attachment->evidence_type] ?? $attachment->evidence_type }}</td>
                        <td class="text-sm"><a class="operator-link" href="{{ route('operator.treasury.projects.evidence.download', [$project->id, $document->id, $attachment->id]) }}">{{ $attachment->original_name }}</a></td>
                        <td class="text-sm text-slate-600">{{ $attachment->note ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $uploaders[$attachment->uploaded_by] ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $attachment->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-sm">
                            @if ($canRemove($attachment))
                                <form method="POST" action="{{ route('operator.treasury.projects.evidence.destroy', [$project->id, $document->id, $attachment->id]) }}">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-secondary">Gỡ</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($canAttach)
        <x-ui.card title="Đính thêm chứng từ">
            <form method="POST" action="{{ route('operator.treasury.projects.evidence.store', [$project->id, $document->id]) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2 p-4" data-testid="treasury-evidence-form">
                @csrf
                <div class="operator-field w-56">
                    <label for="evidence_type">Loại chứng từ</label>
                    <select id="evidence_type" name="evidence_type" class="operator-input" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('evidence_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="evidence_file">File (PDF, ảnh, Word / Excel; tối đa 10 MB)</label>
                    <input id="evidence_file" name="file" type="file" class="operator-input" required accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.docx,.doc">
                </div>
                <div class="operator-field min-w-64 flex-1">
                    <label for="evidence_note">Ghi chú</label>
                    <input id="evidence_note" name="note" type="text" maxlength="500" class="operator-input" value="{{ old('note') }}">
                </div>
                <button type="submit" class="operator-button operator-button-primary">Đính kèm</button>
            </form>
            @if (!in_array($document->status, \App\Services\Treasury\TreasuryEvidenceService::REMOVABLE_STATUSES, true))
                <p class="px-4 pb-4 text-xs text-slate-500">Khoản đã ghi sổ: chứng từ đã đính không gỡ được; đính nhầm thì đính thêm bản đúng kèm ghi chú.</p>
            @endif
        </x-ui.card>
    @endif
@endsection
