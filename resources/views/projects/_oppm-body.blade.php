{{-- GAP-104 body + GAP-110 overlay / decisions; rendered from live data or from a snapshot payload (plain arrays). --}}
@php
    use App\Services\ProjectHealth\ProjectHealthReadService as Health;

    /** @var array<string, mixed> $oppm */
    /** @var array<string, mixed> $header */
    $health = $oppm['health'];
    $progress = $health['progress'];
    $milestones = $health['schedule']['milestones'];
    $attention = $health['attention'];
    $finance = $health['finance'];
    $money = static fn (array $measure): string => $measure['availability'] === Health::AVAILABLE
        ? number_format((float) $measure['value'], 0, ',', '.')
        : 'Chưa có dữ liệu';
    $actionText = static function (?array $action): string {
        if (($action['next_action'] ?? null) === null) {
            return '—';
        }
        $parts = [(string) $action['next_action']];
        if (($action['next_action_owner'] ?? null) !== null) {
            $parts[] = (string) $action['next_action_owner'];
        }
        if (($action['next_action_due'] ?? null) !== null) {
            $parts[] = 'hạn ' . \Illuminate\Support\Carbon::parse($action['next_action_due'])->format('d/m/Y');
        }

        return implode(' · ', $parts);
    };
    $fmtNumber = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $basisLabels = [
        Health::BASIS_COMPONENT_COST_WEIGHTED => 'theo chi phí hạng mục',
        Health::BASIS_TASK_AVERAGE => 'theo công việc, độ tin cậy hạn chế',
    ];
    $statusLabels = [
        'pending' => 'Chưa bắt đầu',
        'in_progress' => 'Đang làm',
        'on_hold' => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];
@endphp

    <x-ui.card title="Thông tin dự án">
        <div class="operator-form-grid">
            <x-ui.field-value label="Mã dự án" :value="$header['code'] ?? '—'" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$header['status']" />
            </x-ui.field-value>
            <x-ui.field-value label="Khách hàng" :value="$header['client'] ?? '—'" />
            <x-ui.field-value label="Quản lý dự án" :value="$header['manager'] ?? '—'" />
            <x-ui.field-value label="Bắt đầu" :value="$header['start_date'] ? \Illuminate\Support\Carbon::parse($header['start_date'])->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Kết thúc" :value="$header['end_date'] ? \Illuminate\Support\Carbon::parse($header['end_date'])->format('d/m/Y') : '—'" />
            <x-ui.field-value
                label="Loại dịch vụ"
                :value="empty($oppm['service_lines']) ? 'Chưa phân loại' : implode(', ', array_column($oppm['service_lines'], 'label'))"
            />
        </div>
        @if ($header['description'])
            <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $header['description'] }}</p>
        @endif
    </x-ui.card>

    <x-ui.card title="Chỉ số điều hành">
        <div class="operator-form-grid">
            <x-ui.field-value
                label="Tiến độ"
                :value="$progress['availability'] === Health::AVAILABLE
                    ? ((int) round((float) $progress['value'])) . '% (' . ($basisLabels[$progress['basis']] ?? '') . ')'
                    : 'Chưa có dữ liệu'"
            />
            <x-ui.field-value label="Thời hạn">
                @if ($health['schedule']['delay']['state'] === \App\Services\ProjectDelayStatus::STATE_COMPLETED)
                    Đã hoàn thành
                @else
                    @include('projects._delay-badge', ['delay' => $health['schedule']['delay']])
                @endif
            </x-ui.field-value>
            <x-ui.field-value
                label="Mốc"
                :value="$milestones['availability'] === Health::AVAILABLE
                    ? $milestones['completed'] . '/' . $milestones['total'] . ' hoàn thành · ' . $milestones['overdue'] . ' quá hạn'
                    : 'Chưa có dữ liệu'"
            />
            <x-ui.field-value
                label="Cần chú ý"
                :value="$attention['blocked_tasks'] . ' việc bị chặn · ' . $attention['overdue_tasks'] . ' việc quá hạn · ' . $attention['blocked_design_items'] . ' hạng mục thiết kế bị chặn'"
            />
        </div>
    </x-ui.card>

    @php $projectNote = $oppm['overlay']['project'] ?? null; $decisions = $oppm['decisions'] ?? ['open' => [], 'open_total' => 0]; @endphp
    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.card title="Tóm tắt quản trị">
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $projectNote['summary'] ?? 'Chưa có tóm tắt.' }}</p>
            <div class="mt-2 text-sm"><span class="font-semibold">Hành động tiếp theo:</span> {{ $actionText($projectNote) }}</div>
            @if ($canEdit)
                <details class="oppm-no-print mt-2">
                    <summary class="operator-link text-xs">Sửa tóm tắt</summary>
                    <form method="POST" action="{{ route('app.projects.oppm.summary.save', $header['id']) }}" class="mt-2 grid gap-2" data-testid="oppm-edit-summary">
                        @csrf
                        <textarea name="summary" maxlength="2000" rows="3" class="operator-input" placeholder="Tóm tắt quản trị">{{ $projectNote['summary'] ?? '' }}</textarea>
                        <input type="text" name="next_action" maxlength="500" class="operator-input" placeholder="Hành động tiếp theo" value="{{ $projectNote['next_action'] ?? '' }}">
                                        <select name="next_action_owner_id" class="operator-input">
                                            <option value="">— Người làm —</option>
                                            @foreach ($users as $userId => $userName)
                                                <option value="{{ $userId }}" @selected(($projectNote['next_action_owner_id'] ?? null) === $userId)>{{ $userName }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="next_action_due" class="operator-input" value="{{ $projectNote['next_action_due'] ?? '' }}">
                        <button type="submit" class="operator-button operator-button-primary">Lưu</button>
                    </form>
                </details>
            @endif
        </x-ui.card>
        <x-ui.card title="Quyết định cần xử lý">
            @forelse ($decisions['open'] as $item)
                <div class="border-b border-slate-100 py-1 text-sm">
                    {{ $item['title'] }}
                    @if ($item['overdue'])<span class="ml-1 rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>@endif
                    <div class="text-xs text-slate-500">Người quyết: {{ $item['maker'] ?? '—' }} · hạn {{ $item['due_date'] ? \Illuminate\Support\Carbon::parse($item['due_date'])->format('d/m/Y') : '—' }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Không có quyết định đang chờ.</p>
            @endforelse
            @if ($snapshot === null)
                <a href="{{ route('app.projects.oppm.decisions', $header['id']) }}" class="oppm-no-print mt-2 inline-block operator-link text-sm">Xem tất cả ({{ $decisions['open_total'] }} đang chờ)</a>
            @elseif ($decisions['open_total'] > count($decisions['open']))
                <p class="mt-2 text-xs text-slate-500">{{ $decisions['open_total'] }} quyết định đang chờ lúc chốt.</p>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Nhóm công việc ({{ $oppm['workstream_total'] }})">
        @if (empty($oppm['workstreams']))
            <p class="text-sm text-slate-500">Chưa có nhóm công việc (dự án chưa có hạng mục).</p>
            @if (!empty($oppm['task_summary']))
                <p class="mt-2 text-sm text-slate-700">
                    Công việc theo trạng thái:
                    @foreach ($oppm['task_summary'] as $status => $count)
                        <span class="mr-3">{{ $statusLabels[$status] ?? $status }}: {{ $count }}</span>
                    @endforeach
                </p>
            @endif
        @else
            <x-ui.data-table :headers="['Nhóm công việc', 'Trạng thái', 'Tiến độ', 'Thời gian', 'Chi phí KH / ghi nhận', 'Bị chặn / quá hạn', 'Kết quả đầu ra / trọng số', 'Hành động tiếp theo']">
                @foreach ($oppm['workstreams'] as $group)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $group['name'] }}</td>
                        <td><x-ui.status-badge :status="$group['status']" /></td>
                        <td class="text-sm">{{ (int) round($group['progress_percent']) }}%</td>
                        <td class="min-w-48 text-xs text-slate-600">
                            {{ $group['start_date'] ?? '—' }} → {{ $group['end_date'] ?? '—' }}
                            @if ($group['timeline'] !== null)
                                <div class="oppm-bar-track mt-1">
                                    <div class="oppm-bar" style="left: {{ $group['timeline']['offset'] }}%; width: {{ $group['timeline']['width'] }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ $group['planned_cost'] !== null ? number_format((float) $group['planned_cost'], 0, ',', '.') : '—' }}
                            /
                            {{ $group['actual_cost'] !== null ? number_format((float) $group['actual_cost'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="text-sm">{{ $group['blocked_tasks'] }} / {{ $group['overdue_tasks'] }}</td>
                        @php $note = $group['overlay'] ?? null; @endphp
                        <td class="text-sm">
                            {{ $note['outcome'] ?? '—' }}
                            @if (($note['weight'] ?? null) !== null)<div class="text-xs text-slate-500">Trọng số {{ $fmtNumber($note['weight']) }}</div>@endif
                            @if (!empty($note['note']))<div class="text-xs text-slate-500">{{ $note['note'] }}</div>@endif
                        </td>
                        <td class="text-sm">
                            {{ $actionText($note) }}
                            @if ($canEdit)
                                <details class="oppm-no-print mt-1">
                                    <summary class="operator-link text-xs">Sửa</summary>
                                    <form method="POST" action="{{ route('app.projects.oppm.workstreams.save', [$header['id'], $group['id']]) }}" class="mt-2 grid gap-2" data-testid="oppm-edit-workstream">
                                        @csrf
                                        <input type="text" name="outcome" maxlength="500" class="operator-input" placeholder="Kết quả đầu ra" value="{{ $note['outcome'] ?? '' }}">
                                        <input type="number" name="weight" min="0" max="100" step="0.01" class="operator-input" placeholder="Trọng số (0–100)" value="{{ $note['weight'] ?? '' }}">
                                        <input type="text" name="next_action" maxlength="500" class="operator-input" placeholder="Hành động tiếp theo" value="{{ $note['next_action'] ?? '' }}">
                                        <select name="next_action_owner_id" class="operator-input">
                                            <option value="">— Người làm —</option>
                                            @foreach ($users as $userId => $userName)
                                                <option value="{{ $userId }}" @selected(($note['next_action_owner_id'] ?? null) === $userId)>{{ $userName }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="next_action_due" class="operator-input" value="{{ $note['next_action_due'] ?? '' }}">
                                        <input type="text" name="note" maxlength="1000" class="operator-input" placeholder="Ghi chú quản trị" value="{{ $note['note'] ?? '' }}">
                                        <input type="number" name="display_order" min="0" max="999" class="operator-input" placeholder="Thứ tự hiển thị" value="{{ $note['display_order'] ?? '' }}">
                                        <button type="submit" class="operator-button operator-button-primary">Lưu</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            @if (($oppm['overlay']['weight_total'] ?? null) !== null)
                <p class="mt-2 text-xs {{ abs($oppm['overlay']['weight_total'] - 100) > 0.001 ? 'text-amber-700' : 'text-slate-500' }}">
                    Tổng trọng số: {{ $fmtNumber($oppm['overlay']['weight_total']) }}@if (abs($oppm['overlay']['weight_total'] - 100) > 0.001) (khác 100)@endif · trọng số chỉ để tham khảo, không làm đổi % tiến độ.
                </p>
            @endif
            @if ($oppm['workstream_total'] > count($oppm['workstreams']))
                <p class="mt-2 text-xs text-slate-500">Hiển thị {{ count($oppm['workstreams']) }}/{{ $oppm['workstream_total'] }} nhóm.</p>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card title="Vướng mắc và trễ hạn">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Việc bị chặn ({{ $oppm['blocked_tasks']['total'] }})</h3>
                @forelse ($oppm['blocked_tasks']['rows'] as $row)
                    <div class="border-b border-slate-100 py-1 text-sm">
                        <a href="/app/tasks/{{ $row['id'] }}" class="operator-link">{{ $row['name'] }}</a>
                        <div class="text-xs text-slate-500">{{ $row['note'] ?? '—' }} · {{ $row['by'] ?? '—' }} · từ {{ $row['since'] ?? '—' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Không có.</p>
                @endforelse
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Việc quá hạn ({{ $oppm['overdue_tasks']['total'] }})</h3>
                @forelse ($oppm['overdue_tasks']['rows'] as $row)
                    <div class="border-b border-slate-100 py-1 text-sm">
                        <a href="/app/tasks/{{ $row['id'] }}" class="operator-link">{{ $row['name'] }}</a>
                        <div class="text-xs text-slate-500">Hạn {{ $row['due'] ?? '—' }} · {{ $row['assignee'] ?? 'chưa giao' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Không có.</p>
                @endforelse
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Hạng mục thiết kế bị chặn ({{ $oppm['blocked_design_items']['total'] }})</h3>
                @forelse ($oppm['blocked_design_items']['rows'] as $row)
                    <div class="border-b border-slate-100 py-1 text-sm">
                        {{ $row['name'] }}
                        <div class="text-xs text-slate-500">{{ $row['note'] ?? '—' }} · {{ $row['by'] ?? '—' }} · từ {{ $row['since'] ?? '—' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Không có.</p>
                @endforelse
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Hợp đồng và tài chính">
        <div class="operator-form-grid">
            <x-ui.field-value label="Ngân sách" :value="$money($finance['budget'])" />
            <x-ui.field-value label="Giá trị hợp đồng" :value="$money($finance['contract_value'])" />
            <x-ui.field-value label="Đã thu" :value="$money($finance['cash_received'])" />
            <x-ui.field-value label="Chi phí ghi nhận (không phải tiền đã chi)" :value="$money($finance['cost_incurred'])" />
        </div>
        @if (!empty($labourCost))
            {{-- GAP-113 V-1: closed payroll months only; total without per-person detail. --}}
            <div class="mt-3 operator-form-grid" data-testid="oppm-labour-cost">
                <x-ui.field-value label="Chi phí nhân công (đã chốt)" :value="number_format((float) $labourCost['amount'], 0, ',', '.') . ' ₫ — ' . rtrim(rtrim(number_format($labourCost['minutes'] / 60, 2, ',', '.'), '0'), ',') . ' giờ, ' . $labourCost['months'] . ' tháng'" />
            </div>
        @endif
        @if (!empty($pnl) && $pnl['months'] > 0)
            {{-- GAP-114 V-1: closed overhead months only. --}}
            <div class="mt-3 operator-form-grid" data-testid="oppm-pnl">
                <x-ui.field-value label="Lời lỗ (đã chốt)" :value="number_format((float) $pnl['profit'], 0, ',', '.') . ' ₫ — thu ' . number_format((float) $pnl['revenue'], 0, ',', '.') . ', chi ' . number_format((float) $pnl['total_cost'], 0, ',', '.') . ' (' . $pnl['months'] . ' tháng)'" />
                <x-ui.field-value label="Lời lỗ dự kiến theo hợp đồng" :value="number_format((float) $pnl['projected_profit'], 0, ',', '.') . ' ₫'" />
            </div>
        @endif
        @if ($canViewTreasury && $finance['treasury']['availability'] === Health::AVAILABLE)
            <div class="mt-3 operator-form-grid">
                <x-ui.field-value label="Ngân quỹ dự án — đang giữ" :value="number_format((float) $finance['treasury']['held_total'], 0, ',', '.')" />
                <x-ui.field-value label="Ngân quỹ dự án — chi đã ghi sổ" :value="number_format((float) $finance['treasury']['posted_expenses'], 0, ',', '.')" />
            </div>
            <div class="oppm-no-print mt-2">
                <x-ui.button-link :href="route('operator.treasury.projects.show', $header['id'])" variant="secondary">Xem Ngân quỹ</x-ui.button-link>
            </div>
        @endif
    </x-ui.card>
