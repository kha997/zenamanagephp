{{-- GAP-107 L-1 / U-1: project period lock — status for everyone, forms and history for lock managers. --}}
<x-ui.card title="Khoá kỳ">
    <div class="space-y-3 p-4">
        @if ($periodLock['locked_through'] === null)
            <p class="text-sm text-slate-600" data-testid="treasury-period-open">Chưa khoá kỳ nào.</p>
        @else
            <p class="text-sm font-medium text-slate-900" data-testid="treasury-period-locked">Đã khoá đến hết ngày {{ $periodLock['locked_through']->format('d/m/Y') }}: không ghi sổ được khoản nào có ngày từ đó trở về trước.</p>
        @endif

        @if ($periodLock['can_manage'])
            <div class="grid gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('operator.treasury.projects.period.lock', $project->id) }}" class="space-y-2" data-testid="treasury-period-lock-form">
                    @csrf
                    <div class="operator-field">
                        <label for="lock_through">Khoá đến hết ngày</label>
                        <input id="lock_through" name="locked_through" type="date" class="operator-input" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="operator-field">
                        <label for="lock_reason">Ghi chú</label>
                        <input id="lock_reason" name="reason" type="text" maxlength="500" class="operator-input">
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Khoá kỳ</button>
                </form>
                @if ($periodLock['locked_through'] !== null)
                    <form method="POST" action="{{ route('operator.treasury.projects.period.unlock', $project->id) }}" class="space-y-2" data-testid="treasury-period-unlock-form">
                        @csrf
                        <div class="operator-field">
                            <label for="unlock_through">Lùi mốc về ngày (để trống = bỏ khoá)</label>
                            <input id="unlock_through" name="locked_through" type="date" class="operator-input">
                        </div>
                        <div class="operator-field">
                            <label for="unlock_reason">Lý do mở khoá <span class="text-rose-600">*</span></label>
                            <input id="unlock_reason" name="reason" type="text" maxlength="500" class="operator-input" required>
                        </div>
                        <button type="submit" class="operator-button operator-button-secondary">Mở khoá</button>
                    </form>
                @endif
            </div>

            @if ($periodLock['history'] !== [])
                <details>
                    <summary class="cursor-pointer text-sm text-slate-600">Lịch sử khoá / mở ({{ count($periodLock['history']) }})</summary>
                    <ul class="mt-2 space-y-1 text-xs text-slate-600">
                        @foreach ($periodLock['history'] as $row)
                            <li>{{ $row->created_at->format('d/m/Y H:i') }} — {{ $row->locked_through ? 'khoá đến ' . $row->locked_through->format('d/m/Y') : 'bỏ khoá' }}{{ $row->reason ? ' — ' . $row->reason : '' }} ({{ $periodLock['users'][$row->created_by] ?? '—' }})</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        @endif
    </div>
</x-ui.card>
