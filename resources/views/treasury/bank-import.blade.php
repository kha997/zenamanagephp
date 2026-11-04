@extends('layouts.operator')

@section('title', 'Import sao kê — ' . $wallet->name)
@section('page_title', 'Import sao kê ngân hàng')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $statusLabels = ['unmatched' => 'Chờ ghép', 'matched' => 'Đã đối soát', 'ignored' => 'Bỏ qua', 'duplicate' => 'Đã có ở lần import trước'];
        $mapLabels = ['date' => 'Ngày *', 'amount' => 'Số tiền (có dấu)', 'debit' => 'Cột Nợ (tiền ra)', 'credit' => 'Cột Có (tiền vào)', 'description' => 'Diễn giải', 'reference' => 'Số tham chiếu'];
    @endphp

    <x-ui.page-header
        title="Import sao kê — {{ $wallet->name }}"
        description="{{ $project->name }}. Hệ thống chỉ gợi ý ghép dòng sao kê với giao dịch đã ghi sổ; không tự tạo khoản thu / chi."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.wallets.reconcile', [$project->id, $wallet->id])" variant="secondary">Đối soát ví</x-ui.button-link>
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    @include('treasury._errors')
    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif

    @if ($import === null)
        @if ($canReconcile)
            <x-ui.card title="Tải sao kê (CSV)">
                <form method="POST" action="{{ route('operator.treasury.projects.wallets.bank-import.store', [$project->id, $wallet->id]) }}" enctype="multipart/form-data" class="space-y-3 p-4" data-testid="treasury-bank-import-form">
                    @csrf
                    <div class="operator-form-grid">
                        <div class="operator-field">
                            <label for="bank_file">File CSV (tối đa 2 MB, 5.000 dòng; Excel: lưu thành "CSV UTF-8")</label>
                            <input id="bank_file" name="file" type="file" accept=".csv,.txt" class="operator-input" required>
                        </div>
                        <div class="operator-field">
                            <label for="statement_reference">Số / tên sao kê *</label>
                            <input id="statement_reference" name="statement_reference" type="text" maxlength="100" class="operator-input" value="{{ old('statement_reference') }}" required>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600">Chọn số thứ tự cột (cột đầu tiên là 0). Dùng một cột "Số tiền" có dấu, hoặc hai cột Nợ / Có.</p>
                    <div class="operator-form-grid">
                        @foreach ($mapLabels as $key => $label)
                            <div class="operator-field">
                                <label for="map_{{ $key }}">{{ $label }}</label>
                                <input id="map_{{ $key }}" name="map[{{ $key }}]" type="number" min="0" max="50" class="operator-input" value="{{ old('map.' . $key, $columnMap[$key] ?? '') }}">
                            </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="has_header" value="0">
                    <label class="text-sm"><input type="checkbox" name="has_header" value="1" checked> Dòng đầu là tiêu đề cột</label>
                    <div><button type="submit" class="operator-button operator-button-primary">Import</button></div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card title="Các lần import">
            @if ($imports === [])
                <p class="p-4 text-sm text-slate-500">Chưa import sao kê nào.</p>
            @else
                <x-ui.data-table :headers="['Ngày import', 'Sao kê', 'File', 'Số dòng']">
                    @foreach ($imports as $row)
                        <tr data-testid="treasury-bank-import-row">
                            <td class="text-sm">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-sm"><a class="operator-link" href="{{ route('operator.treasury.projects.bank-imports.show', [$project->id, $row->id]) }}">{{ $row->statement_reference }}</a></td>
                            <td class="text-sm text-slate-600">{{ $row->original_name }}</td>
                            <td class="text-sm">{{ $row->line_count }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>
    @else
        <x-ui.card title="Sao kê {{ $import->statement_reference }} ({{ $import->line_count }} dòng)">
            <form method="POST" action="{{ route('operator.treasury.projects.bank-imports.confirm', [$project->id, $import->id]) }}" id="bank-confirm-form">@csrf</form>
            <x-ui.data-table :headers="['#', 'Ngày', 'Chiều', 'Số tiền', 'Diễn giải / tham chiếu', 'Trạng thái', 'Gợi ý ghép', '']">
                @foreach ($lines as $line)
                    @php $suggestion = $suggestions[(string) $line->id] ?? null; @endphp
                    <tr data-testid="treasury-bank-line">
                        <td class="text-sm">{{ $line->line_no }}</td>
                        <td class="text-sm">{{ $line->transaction_date->format('d/m/Y') }}</td>
                        <td class="text-sm">{{ $line->direction === 'in' ? 'Vào' : 'Ra' }}</td>
                        <td class="text-sm font-semibold">{{ $money($line->amount) }}</td>
                        <td class="text-sm text-slate-600">{{ $line->description ?? '—' }}{{ $line->bank_reference ? ' · ' . $line->bank_reference : '' }}{{ $line->note ? ' · ' . $line->note : '' }}</td>
                        <td class="text-sm">{{ $statusLabels[$line->status] ?? $line->status }}</td>
                        <td class="text-sm">
                            @if ($suggestion !== null && $canReconcile)
                                <label data-testid="treasury-bank-suggestion">
                                    <input type="checkbox" form="bank-confirm-form" name="pairs[{{ $line->id }}]" value="{{ $suggestion['ledger_entry_id'] }}" checked>
                                    {{ $suggestion['transaction_date'] ? \Illuminate\Support\Carbon::parse($suggestion['transaction_date'])->format('d/m/Y') : '' }} — {{ $suggestion['reference'] ?? $suggestion['description'] ?? '' }}
                                </label>
                            @elseif ($line->status === 'unmatched')
                                <span class="text-slate-500">Không có giao dịch khớp</span>
                            @endif
                        </td>
                        <td class="text-sm">
                            @if ($line->status === 'unmatched' && $canReconcile)
                                <details>
                                    <summary class="operator-button operator-button-inline">Bỏ qua</summary>
                                    <form method="POST" action="{{ route('operator.treasury.projects.bank-imports.lines.ignore', [$project->id, $import->id, $line->id]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="text" name="note" class="operator-input" placeholder="Lý do" required maxlength="500">
                                        <button type="submit" class="operator-button operator-button-secondary">Xác nhận</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            @if ($canReconcile && $suggestions !== [])
                <div class="p-4"><button type="submit" form="bank-confirm-form" class="operator-button operator-button-primary" data-testid="treasury-bank-confirm">Xác nhận đối soát các dòng đã chọn</button></div>
            @endif
        </x-ui.card>
        <div class="mt-2"><x-ui.button-link :href="route('operator.treasury.projects.wallets.bank-import', [$project->id, $wallet->id])" variant="secondary">Các lần import của ví</x-ui.button-link></div>
    @endif
@endsection
