@extends('layouts.operator')

@section('title', 'Hợp đồng ' . $contract->code)
@section('page_title', 'Hợp đồng ' . $contract->code)

@section('content')
    <x-ui.page-header
        :title="'Hợp đồng ' . $contract->code"
        :description="$contract->title"
    >
        <x-ui.button-link :href="route('operator.contracts.pdf', $contract->id)" variant="secondary">Tải PDF</x-ui.button-link>
        <x-ui.button-link :href="route('operator.contracts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if ($hasQuoteDrift)
        <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
            Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp.
        </p>
    @endif

    <div class="space-y-6">
        <x-ui.card title="Thông tin hợp đồng">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field-value label="Mã" :value="$contract->code" />
                <x-ui.field-value label="Loại hợp đồng" :value="$contract->typeLabel()" />
                <x-ui.field-value label="Dự án" :value="($contract->project?->name ?? '—') . ($contract->project?->code ? ' (' . $contract->project->code . ')' : '')" />
                <x-ui.field-value label="Trạng thái" :value="match($contract->status) { 'active' => 'Đang hiệu lực', 'draft' => 'Nháp', 'closed' => 'Đã đóng', 'cancelled' => 'Đã hủy', default => $contract->status }" />
                <x-ui.field-value label="Giá trị" :value="$contract->total_value !== null ? number_format((float) $contract->total_value) . ' ' . $contract->currency : null" />
                <x-ui.field-value label="Ngày ký" :value="optional($contract->signed_at)->format('d/m/Y')" />
                <x-ui.field-value label="Hiệu lực" :value="(optional($contract->start_date)->format('d/m/Y') ?? '—') . ' → ' . (optional($contract->end_date)->format('d/m/Y') ?? '—')" />
            </div>
        </x-ui.card>

        <x-ui.card title="Tài chính hợp đồng">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field-value label="Tổng giá trị HĐ" :value="number_format($finance['total_value']) . ' ' . $contract->currency" />
                <x-ui.field-value label="Đã thu" :value="number_format($finance['paid_total']) . ' ' . $contract->currency" />
                <x-ui.field-value label="Còn phải thu" :value="number_format($finance['remaining']) . ' ' . $contract->currency" />
                <x-ui.field-value label="Quá hạn" :value="$finance['overdue_count'] . ' đợt'" />
            </div>

            <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-700">Các đợt thu</h3>
            @forelse ($payments as $payment)
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                    <span class="font-medium">{{ $payment->name }}</span>
                    <span>{{ number_format((float) $payment->amount) }}</span>
                    <span class="text-slate-500">hạn {{ optional($payment->due_date)->format('d/m/Y') ?? '—' }}</span>
                    <x-ui.status-badge :status="$payment->status" />
                    @if ($payment->paid_at)
                        <span class="text-emerald-600">thu ngày {{ $payment->paid_at->format('d/m/Y') }}</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Chưa có đợt thu.</p>
            @endforelse

            <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-700">Các khoản chi (ghi tay)</h3>
            @forelse ($expenses as $expense)
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                    <span class="text-slate-500">{{ $expense->expense_date->format('d/m/Y') }}</span>
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $expense->categoryLabel() }}</span>
                    <span class="font-medium">{{ $expense->description }}</span>
                    <span>{{ number_format((float) $expense->amount) }}</span>
                    @if (auth()->user()?->hasPermission('contract.expense.delete'))
                        <form method="POST" action="{{ route('operator.contracts.expenses.delete', [$contract->id, $expense->id]) }}">
                            @csrf
                            <button type="submit" class="text-xs text-rose-600 hover:underline">Xóa</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">Chưa có khoản chi ghi tay.</p>
            @endforelse
            <div class="mt-2 text-sm font-medium">Tổng chi ghi tay: {{ number_format($finance['manual_expense_total']) }} {{ $contract->currency }}</div>

            @if (auth()->user()?->hasPermission('contract.expense.create'))
                <form method="POST" action="{{ route('operator.contracts.expenses.store', $contract->id) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="operator-field">
                        <label for="expense_date" class="text-xs">Ngày</label>
                        <input id="expense_date" name="expense_date" type="date" class="operator-input" value="{{ old('expense_date') }}" required>
                    </div>
                    <div class="operator-field">
                        <label for="category" class="text-xs">Nhóm</label>
                        <select id="category" name="category" class="operator-select" required>
                            <option value="labor">Nhân công</option>
                            <option value="subcontractor">Thầu phụ</option>
                            <option value="design_outsource">Thuê ngoài thiết kế</option>
                            <option value="misc">Khác</option>
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="description" class="text-xs">Diễn giải</label>
                        <input id="description" name="description" type="text" class="operator-input" value="{{ old('description') }}" maxlength="1000" required>
                    </div>
                    <div class="operator-field">
                        <label for="amount" class="text-xs">Số tiền</label>
                        <input id="amount" name="amount" type="number" min="1" step="any" class="operator-input" value="{{ old('amount') }}" required>
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Ghi chi</button>
                </form>
                @error('amount')<div class="text-sm text-rose-600">{{ $message }}</div>@enderror
                @error('category')<div class="text-sm text-rose-600">{{ $message }}</div>@enderror
                @error('description')<div class="text-sm text-rose-600">{{ $message }}</div>@enderror
                @error('expense_date')<div class="text-sm text-rose-600">{{ $message }}</div>@enderror
            @endif

            <div class="mt-4 border-t border-slate-200 pt-3 text-sm">
                @if ($finance['material_cost_total'] !== null)
                    <div>Chi vật tư theo phiếu nhận (tự động): {{ number_format($finance['material_cost_total']) }} {{ $contract->currency }}</div>
                @else
                    <div class="text-slate-500">Chi vật tư tự động: không tải được — chưa tính vào tổng chi. {{ $summaryUnavailableMessage ?? '' }}</div>
                @endif
                <div class="mt-1 font-medium">Tổng chi: {{ number_format($finance['expense_total']) }} {{ $contract->currency }}</div>
                <div class="mt-1 text-base font-semibold">Đã thu − đã chi: {{ number_format($finance['balance']) }} {{ $contract->currency }}</div>
            </div>
        </x-ui.card>

        @if ($progress['type'] === 'design')
            @include('projects._design-progress', ['designItems' => $progress['designItems'], 'blockedItems' => $progress['blockedItems'], 'tasks' => null])
        @elseif ($progress['type'] === 'construction')
            <x-ui.card title="Tiến độ thi công">
                <div class="mb-3 flex flex-wrap gap-4 text-sm text-slate-600">
                    <span>Nghiệm thu: {{ $progress['inspectionCount'] }}</span>
                    <span>NCR đang mở: {{ $progress['openNcrCount'] }}</span>
                    <span>Phiếu nhận vật tư: {{ $progress['receiptCount'] }}</span>
                </div>
                @forelse ($progress['tasks'] as $task)
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                        <span class="font-medium">{{ $task->title ?? $task->name }}</span>
                        <x-ui.status-badge :status="$task->status" />
                        @if ($task->blocked_at)
                            <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Vướng</span>
                        @endif
                        <span class="text-slate-500">{{ $task->assignee?->name ?? 'Chưa giao' }}</span>
                        <span class="text-slate-400">{{ (int) $task->progress_percent }}%</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Chưa có công việc.</p>
                @endforelse
            </x-ui.card>

            {{-- BOQ Card --}}
            @php $boqLocked = $certificates->contains('status', \App\Models\PaymentCertificate::STATUS_APPROVED); @endphp
            <x-ui.card title="Bảng khối lượng HĐ">
                @if ($boq && $boqLines->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs font-medium uppercase text-slate-500">
                                    <th class="px-2 py-2">Mã</th>
                                    <th class="px-2 py-2">Tên</th>
                                    <th class="px-2 py-2">ĐVT</th>
                                    <th class="px-2 py-2 text-right">KL HĐ</th>
                                    <th class="px-2 py-2 text-right">Đơn giá</th>
                                    <th class="px-2 py-2 text-right">Thành tiền</th>
                                    @if (!$boqLocked && auth()->user()?->hasPermission('contract.update'))
                                        <th class="px-2 py-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($boqLines as $item)
                                    <tr class="border-b border-slate-100">
                                        <td class="px-2 py-2 font-medium">{{ $item->code }}</td>
                                        <td class="px-2 py-2">{{ $item->name }}</td>
                                        <td class="px-2 py-2">{{ $item->unit }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                                        <td class="px-2 py-2 text-right">{{ $item->unit_price !== null ? number_format($item->unit_price, 0, ',', '.') : '—' }}</td>
                                        <td class="px-2 py-2 text-right font-medium">{{ $item->unit_price !== null ? number_format($item->quantity * $item->unit_price, 0, ',', '.') : '—' }}</td>
                                        @if (!$boqLocked && auth()->user()?->hasPermission('contract.update'))
                                            <td class="px-2 py-2 text-right">
                                                <form method="POST" action="{{ route('operator.contracts.boq-lines.delete', [$contract->id, $item->id]) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-xs text-rose-600 hover:underline" onclick="return confirm('Xóa dòng này?')">Xóa</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-slate-500">Chưa có bảng khối lượng.</p>
                @endif

                @if ($boqLocked)
                    <p class="mt-2 text-xs text-amber-600">Bảng khối lượng đã khóa (đã có chứng chỉ được duyệt).</p>
                @endif

                @if (auth()->user()?->hasPermission('contract.update'))
                    <form method="POST" action="{{ route('operator.contracts.boq-lines.store', $contract->id) }}" class="mt-3 flex flex-wrap items-end gap-2" @if($boqLocked) style="display:none" @endif>
                        @csrf
                        <div class="operator-field">
                            <label for="boq_code" class="text-xs">Mã</label>
                            <input id="boq_code" name="code" type="text" class="operator-input" value="{{ old('boq_code') }}" maxlength="100" required>
                        </div>
                        <div class="operator-field">
                            <label for="boq_name" class="text-xs">Tên</label>
                            <input id="boq_name" name="name" type="text" class="operator-input" value="{{ old('boq_name') }}" maxlength="255" required>
                        </div>
                        <div class="operator-field">
                            <label for="boq_unit" class="text-xs">ĐVT</label>
                            <input id="boq_unit" name="unit" type="text" class="operator-input" value="{{ old('boq_unit') }}" maxlength="50" required>
                        </div>
                        <div class="operator-field">
                            <label for="boq_quantity" class="text-xs">KL</label>
                            <input id="boq_quantity" name="quantity" type="number" min="0.01" step="any" class="operator-input" value="{{ old('boq_quantity') }}" required>
                        </div>
                        <div class="operator-field">
                            <label for="boq_unit_price" class="text-xs">Đơn giá</label>
                            <input id="boq_unit_price" name="unit_price" type="number" min="0" step="any" class="operator-input" value="{{ old('boq_unit_price') }}">
                        </div>
                        <button type="submit" class="operator-button operator-button-primary">Thêm dòng</button>
                    </form>
                @endif
            </x-ui.card>

            {{-- Certificates Card --}}
            <x-ui.card title="Nghiệm thu khối lượng">
                @forelse ($certificates as $cert)
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                        <span class="font-medium">Kỳ {{ $cert->period_no }}</span>
                        <span class="text-slate-500">{{ $cert->period_from->format('d/m/Y') }} → {{ $cert->period_to->format('d/m/Y') }}</span>
                        <span>{{ number_format($cert->total_this_period) }}</span>
                        <x-ui.status-badge :status="$cert->status" />
                        <a href="{{ route('operator.contracts.certificates.show', [$contract->id, $cert->id]) }}" class="text-xs text-blue-600 hover:underline">Chi tiết</a>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Chưa có chứng chỉ nghiệm thu.</p>
                @endforelse

                @if (auth()->user()?->hasPermission('payment_certificate.create'))
                    <form method="POST" action="{{ route('operator.contracts.certificates.store', $contract->id) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="operator-field">
                            <label for="cert_period_from" class="text-xs">Từ ngày</label>
                            <input id="cert_period_from" name="period_from" type="date" class="operator-input" value="{{ old('period_from') }}" required>
                        </div>
                        <div class="operator-field">
                            <label for="cert_period_to" class="text-xs">Đến ngày</label>
                            <input id="cert_period_to" name="period_to" type="date" class="operator-input" value="{{ old('period_to') }}" required>
                        </div>
                        <button type="submit" class="operator-button operator-button-primary">Tạo chứng chỉ</button>
                    </form>
                @endif
            </x-ui.card>
        @else
            <x-ui.card title="Tiến độ">
                <p class="text-sm text-slate-500">Hợp đồng chưa phân loại — chọn loại hợp đồng để xem tiến độ tương ứng.</p>
            </x-ui.card>
        @endif
    </div>
@endsection
