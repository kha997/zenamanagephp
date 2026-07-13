@extends('layouts.operator')

@section('title', 'Nghiệm thu Kỳ ' . $certificate->period_no . ' — HĐ ' . $contract->code)
@section('page_title', 'Nghiệm thu Kỳ ' . $certificate->period_no)

@section('content')
    <x-ui.page-header
        :title="'Nghiệm thu Kỳ ' . $certificate->period_no"
        :description="$contract->code . ' — ' . $contract->title"
    >
        <x-ui.button-link :href="route('operator.contracts.show', $contract->id)" variant="secondary">Quay lại hợp đồng</x-ui.button-link>
    </x-ui.page-header>

    <div class="space-y-6">
        {{-- Header info --}}
        <x-ui.card title="Thông tin chứng chỉ">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field-value label="Kỳ" :value="'Kỳ ' . $certificate->period_no" />
                <x-ui.field-value label="Từ ngày" :value="$certificate->period_from->format('d/m/Y')" />
                <x-ui.field-value label="Đến ngày" :value="$certificate->period_to->format('d/m/Y')" />
                <x-ui.field-value label="Tổng kỳ này" :value="number_format($certificate->total_this_period)" />
            </div>
            <div class="mt-3">
                <span class="text-xs font-medium text-slate-500">Trạng thái: </span>
                <x-ui.status-badge :status="$certificate->status" />
            </div>
        </x-ui.card>

        {{-- Line summaries table --}}
        <x-ui.card title="Khối lượng chi tiết">
            @if (empty($lineSummaries))
                <p class="text-sm text-slate-500">Chưa có dòng BOQ nào.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs font-medium uppercase text-slate-500">
                                <th class="px-2 py-2">Mã</th>
                                <th class="px-2 py-2">Tên</th>
                                <th class="px-2 py-2 text-right">KL HĐ</th>
                                <th class="px-2 py-2 text-right">Lũy kế trước</th>
                                <th class="px-2 py-2 text-right">Kỳ này</th>
                                <th class="px-2 py-2 text-right">Còn lại</th>
                                <th class="px-2 py-2 text-right">%</th>
                                <th class="px-2 py-2 text-right">Đơn giá</th>
                                <th class="px-2 py-2 text-right">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($boqLines as $item)
                                @php $summary = $lineSummaries[$item->id] ?? null; @endphp
                                @if ($summary)
                                    <tr class="border-b border-slate-100 {{ $summary['over_quantity'] ? 'bg-amber-50' : '' }}">
                                        <td class="px-2 py-2 font-medium">{{ $item->code }}</td>
                                        <td class="px-2 py-2">{{ $item->name }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($summary['contract_qty'], 0, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($summary['prev_qty'], 0, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">
                                            @if ($certificate->status === 'draft' && auth()->user()?->hasPermission('payment_certificate.create'))
                                                <form method="POST" action="{{ route('operator.contracts.certificates.lines.save', [$contract->id, $certificate->id]) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="lines[{{ $item->id }}]" value="{{ $summary['this_qty'] }}" class="w-20 text-right operator-input" />
                                                </form>
                                                <input type="number" min="0" step="any" value="{{ $summary['this_qty'] }}" class="w-20 text-right operator-input"
                                                    hx-trigger="change" hx-include="closest form"
                                                    hx-name="lines[{{ $item->id }}]"
                                                    />
                                            @else
                                                {{ number_format($summary['this_qty'], 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">{{ number_format($summary['remaining_qty'], 0, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">
                                            {{ number_format($summary['percent_done'], 1) }}%
                                            @if ($summary['over_quantity'])
                                                <span class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 text-xs font-medium text-amber-800">Vượt KL</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">{{ $summary['unit_price'] !== null ? number_format($summary['unit_price'], 0, ',', '.') : '—' }}</td>
                                        <td class="px-2 py-2 text-right font-medium">{{ number_format($summary['amount_this_period'], 0, ',', '.') }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-semibold">
                                <td colspan="8" class="px-2 py-2 text-right">Tổng kỳ này</td>
                                <td class="px-2 py-2 text-right">{{ number_format($certificate->total_this_period, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-ui.card>

        {{-- Action buttons --}}
        @if ($certificate->status === 'draft')
            <div class="flex flex-wrap gap-3">
                @if (auth()->user()?->hasPermission('payment_certificate.create'))
                    <form method="POST" action="{{ route('operator.contracts.certificates.submit', [$contract->id, $certificate->id]) }}">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Gửi nghiệm thu</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($certificate->status === 'submitted' && auth()->user()?->hasPermission('payment_certificate.approve'))
            <form method="POST" action="{{ route('operator.contracts.certificates.approve', [$contract->id, $certificate->id]) }}">
                @csrf
                <button type="submit" class="operator-button operator-button-primary">Duyệt nghiệm thu</button>
            </form>
        @endif
    </div>
@endsection
