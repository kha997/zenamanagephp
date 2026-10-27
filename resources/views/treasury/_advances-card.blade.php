{{-- GAP-096 S5: advances on the project Treasury page (request form, pending requests, ageing). --}}
@inject('advanceService', 'App\Services\Treasury\TreasuryAdvanceService')
@php
    $advanceAgeing = $advanceService->ageing($project);
    $pendingAdvances = $advanceService->pendingDocuments($project);
    $bucketLabels = ['under_30' => 'Dưới 30 ngày', '30_60' => '30–60 ngày', '60_90' => '60–90 ngày', 'over_90' => 'Trên 90 ngày'];
@endphp

<x-ui.card title="Tạm ứng">
    <div class="space-y-5 p-4">
        @if ($can['createExpense'] && $transferSources->isNotEmpty())
            <details @if (old('advance_form')) open @endif>
                <summary class="operator-button operator-button-secondary">Đề nghị tạm ứng</summary>
                <form method="POST" action="{{ route('operator.treasury.projects.advances.store', $project->id) }}" class="mt-3 space-y-5" data-testid="treasury-advance-form">
                    @csrf
                    <input type="hidden" name="advance_form" value="1">
                    <div class="operator-form-grid">
                        <div class="operator-field">
                            <label for="adv_wallet">Chi từ ví <span class="text-rose-600">*</span></label>
                            <select id="adv_wallet" name="source_wallet_id" class="operator-input" required>
                                @foreach ($transferSources as $wallet)
                                    <option value="{{ $wallet->id }}" @selected(old('source_wallet_id') === $wallet->id)>{{ $wallet->name }} ({{ $money($summary['wallets'][$wallet->id] ?? 0) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="adv_party">Người nhận tạm ứng <span class="text-rose-600">*</span></label>
                            <select id="adv_party" name="destination_party_id" class="operator-input" required>
                                @foreach ($parties as $party)
                                    <option value="{{ $party->id }}" @selected(old('destination_party_id') === $party->id)>{{ $party->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @include('treasury._money-fields', ['prefix' => 'adv'])
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" name="action" value="draft" class="operator-button operator-button-secondary">Lưu nháp</button>
                        @if ($can['submitExpense'])
                            <button type="submit" name="action" value="submit" class="operator-button operator-button-primary">Lưu &amp; gửi duyệt</button>
                        @endif
                    </div>
                </form>
            </details>
        @endif

        @if ($pendingAdvances->isNotEmpty())
            <x-ui.data-table :headers="['Ngày', 'Chi từ ví', 'Người nhận', 'Số tiền', 'Trạng thái', 'Thao tác']">
                @foreach ($pendingAdvances as $pending)
                    <tr data-testid="treasury-advance-pending-row">
                        <td class="text-sm">{{ $pending->transaction_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $pending->sourceWallet?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $pending->destinationParty?->name ?? '—' }}</td>
                        <td class="text-sm font-semibold">{{ $money($pending->amount) }}</td>
                        <td class="text-sm">{{ $statusLabels[$pending->status] ?? $pending->status }}</td>
                        <td class="text-sm">
                            @php $advanceAction = fn ($a) => route('operator.treasury.projects.advances.action', [$project->id, $pending->id, $a]); @endphp
                            @if ($pending->status === 'draft' && $can['submitExpense'] && (string) $pending->created_by === (string) auth()->id())
                                <form method="POST" action="{{ $advanceAction('submit') }}">@csrf<button type="submit" class="operator-button operator-button-inline">Gửi duyệt</button></form>
                            @elseif ($pending->status === 'submitted' && $can['approveExpense'])
                                <form method="POST" action="{{ $advanceAction('approve') }}" class="inline" data-testid="treasury-advance-approve-form">@csrf<button type="submit" class="operator-button operator-button-primary">Duyệt &amp; chi</button></form>
                                <details>
                                    <summary class="operator-button operator-button-inline">Từ chối</summary>
                                    <form method="POST" action="{{ $advanceAction('reject') }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="text" name="note" class="operator-input" placeholder="Lý do từ chối" required>
                                        <button type="submit" class="operator-button operator-button-primary">Xác nhận từ chối</button>
                                    </form>
                                </details>
                            @elseif ($pending->status === 'rejected' && $can['createExpense'])
                                <form method="POST" action="{{ $advanceAction('copy') }}">@csrf<button type="submit" class="operator-button operator-button-inline">Sao chép thành nháp mới</button></form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif

        <div class="operator-form-grid" data-testid="treasury-advance-ageing-totals">
            @foreach ($bucketLabels as $bucket => $label)
                <x-ui.field-value :label="'Còn nợ ' . mb_strtolower($label)" :value="$money($advanceAgeing['totals'][$bucket])" />
            @endforeach
        </div>
        @if ($advanceAgeing['rows'] === [])
            <x-ui.empty-state title="Không có tạm ứng nào còn nợ" description="Tạm ứng đã chi và chưa quyết toán hết hiện ở đây, theo tuổi nợ." />
        @else
            <x-ui.data-table :headers="['Ngày chi', 'Người nhận', 'Tạm ứng', 'Đã quyết toán', 'Đã nộp lại', 'Còn nợ', 'Tuổi nợ', '']">
                @foreach ($advanceAgeing['rows'] as $row)
                    <tr data-testid="treasury-advance-ageing-row">
                        <td class="text-sm">{{ \Illuminate\Support\Carbon::parse($row['posted_on'])->format('d/m/Y') }}</td>
                        <td class="text-sm">{{ $row['party_name'] }}</td>
                        <td class="text-sm">{{ $money($row['amount']) }}</td>
                        <td class="text-sm">{{ $money($row['settled']) }}</td>
                        <td class="text-sm">{{ $money($row['returned']) }}</td>
                        <td class="text-sm font-semibold">{{ $money($row['outstanding']) }}</td>
                        <td class="text-sm">{{ $row['days'] }} ngày — {{ $bucketLabels[$row['bucket']] }}@if ($row['open_request']) <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Chờ duyệt quyết toán</span>@endif</td>
                        <td class="text-sm"><a href="{{ route('operator.treasury.projects.advances.show', [$project->id, $row['id']]) }}" class="operator-button operator-button-inline">Mở</a></td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </div>
</x-ui.card>
