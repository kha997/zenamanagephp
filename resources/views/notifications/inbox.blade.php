@extends('layouts.operator')

@section('title', 'Thông báo')
@section('page_title', 'Thông báo')

@section('content')
    {{-- GAP-124 B-1 (Owner amendment 1: new file; the older orphan notifications/index.blade.php is left as is): the signed-in user's notifications. Opening one marks it read and follows its link. --}}
    <x-ui.page-header title="Thông báo" description="Mới nhất ở trên. Bấm vào một thông báo để mở đúng việc.">
        <x-ui.button-link :href="route('app.notifications.index', $unreadOnly ? [] : ['unread' => 1])" variant="secondary">{{ $unreadOnly ? 'Xem tất cả' : 'Chỉ chưa đọc' }}</x-ui.button-link>
        <form method="POST" action="{{ route('app.notifications.read-all') }}" class="inline">
            @csrf
            <button type="submit" class="operator-button operator-button-secondary">Đánh dấu đã đọc hết</button>
        </form>
    </x-ui.page-header>

    <x-ui.card>
        @if ($notifications->isEmpty())
            <x-ui.empty-state :title="$unreadOnly ? 'Không có thông báo chưa đọc' : 'Chưa có thông báo'" description="Thông báo về hồ sơ, phiếu không đạt, việc quá hạn… sẽ hiện ở đây." />
        @else
            <ul class="operator-notification-list" data-testid="notification-list">
                @foreach ($notifications as $notification)
                    <li class="operator-notification-item {{ $notification->read_at === null ? 'is-unread' : '' }}">
                        <a href="{{ route('app.notifications.open', $notification->id) }}" class="operator-notification-link">
                            <span class="operator-notification-title">{{ $notification->title }}</span>
                            @if ($notification->body)
                                <span class="operator-notification-body">{{ \Illuminate\Support\Str::limit((string) $notification->body, 240) }}</span>
                            @endif
                            <span class="operator-notification-time">{{ $notification->created_at?->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="p-3">{{ $notifications->links() }}</div>
        @endif
    </x-ui.card>
@endsection
