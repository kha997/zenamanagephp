{{-- GAP-122 L-1 / A-1: phone cards for "Duyệt giờ", grouped by person. Each button is its own form posting to the existing
     review action with exactly the ids it acts on; no tick boxes, and outside the desktop table's form. --}}
<div class="operator-card-list" data-testid="time-review-cards">
    @foreach ($entries->groupBy('user_id') as $personEntries)
        @php
            $person = $personEntries->first()?->user?->name ?? '—';
            $count = $personEntries->count();
        @endphp
        <section class="operator-review-group" data-testid="time-review-group">
            <div class="operator-review-group-head">
                <span class="operator-item-card-title">{{ $person }}</span>
                <span class="operator-item-card-meta" data-testid="time-review-group-total">{{ $count }} dòng · <strong class="text-slate-900">{{ $hm((int) $personEntries->sum('minutes')) }}</strong></span>
            </div>
            @foreach ($personEntries as $entry)
                <div class="operator-item-card" data-testid="time-review-card">
                    <div class="flex items-start justify-between gap-2">
                        <span class="operator-item-card-title">{{ $entry->label() }}</span>
                        <span class="operator-review-minutes">{{ $hm($entry->minutes) }}</span>
                    </div>
                    <div class="operator-item-card-meta">{{ $entry->work_date->format('d/m') }} · {{ $entry->project?->name ?? 'Không thuộc công trình' }}</div>
                    @if ($entry->auto_stopped || $entry->source === 'manual')
                        <div>
                            @if ($entry->auto_stopped)<span class="rounded bg-amber-100 px-1 text-xs text-amber-800">Tự dừng</span>@endif
                            @if ($entry->source === 'manual')<span class="rounded bg-slate-100 px-1 text-xs">Nhập tay</span>@endif
                        </div>
                    @endif
                    @if ($entry->note)<div class="text-xs text-slate-600">{{ $entry->note }}</div>@endif
                    <div class="operator-review-actions">
                        <form method="POST" action="{{ route('app.time.review.act') }}" data-testid="time-review-card-approve">
                            @csrf
                            <input type="hidden" name="ids[]" value="{{ $entry->id }}">
                            <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                            <button type="submit" name="action" value="approve" class="operator-button operator-button-primary operator-button-touch">Duyệt</button>
                        </form>
                        <details class="operator-review-reject">
                            <summary class="operator-button operator-button-secondary operator-button-touch">Từ chối</summary>
                            <form method="POST" action="{{ route('app.time.review.act') }}" class="mt-2 grid gap-2" data-testid="time-review-card-reject">
                                @csrf
                                <input type="hidden" name="ids[]" value="{{ $entry->id }}">
                                <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                                <input type="text" name="reason" maxlength="500" required class="operator-input" placeholder="Lý do từ chối">
                                <button type="submit" name="action" value="reject" class="operator-button operator-button-secondary operator-button-touch">Xác nhận từ chối</button>
                            </form>
                        </details>
                    </div>
                </div>
            @endforeach
            @if ($count > 1)
                <form method="POST" action="{{ route('app.time.review.act') }}" data-testid="time-review-group-approve">
                    @csrf
                    @foreach ($personEntries as $entry)
                        <input type="hidden" name="ids[]" value="{{ $entry->id }}">
                    @endforeach
                    <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                    <button type="submit" name="action" value="approve" class="operator-button operator-button-secondary operator-button-touch w-full">Duyệt cả {{ $count }} dòng của {{ $person }}</button>
                </form>
            @endif
        </section>
    @endforeach
</div>
