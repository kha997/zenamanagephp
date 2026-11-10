{{-- GAP-118 T-1: fixed bottom bar while the user's timer runs; Stop uses the existing app.time.stop route. --}}
@if ($running)
    <div class="operator-running-bar-spacer" aria-hidden="true"></div>
    <div class="operator-running-bar" data-testid="running-timer-bar" role="status">
        <span>Đang ghi giờ: <strong>{{ $running->label() }}</strong> từ {{ $running->started_at?->format('H:i') }}</span>
        <form method="POST" action="{{ route('app.time.stop') }}">
            @csrf
            <button type="submit" class="operator-button operator-button-secondary operator-button-touch">■ Dừng</button>
        </form>
    </div>
@endif
