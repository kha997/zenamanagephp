@extends('layouts.operator')

@section('title', 'Hôm nay')
@section('page_title', 'Hôm nay')

@section('content')
    <section>
        <h2 class="text-base font-semibold">Việc của tôi</h2>
        @if ($workspace->personalOpenWork->items === [])
            @if ($workspace->personalOpenWork->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Bạn chưa có việc nào đang mở.</p>
            @endif
        @else
            @include('app._today-open-work-table', ['items' => $workspace->personalOpenWork->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Đang thực hiện</h2>
        @if ($workspace->inProgress->items === [])
            @if ($workspace->inProgress->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Bạn chưa có việc nào đang thực hiện.</p>
            @endif
        @else
            @include('app._today-open-work-table', ['items' => $workspace->inProgress->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Quá hạn và bị chặn</h2>
        @if ($workspace->overdueAndBlocked->items === [])
            @if ($workspace->overdueAndBlocked->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Không có việc nào quá hạn hoặc bị chặn.</p>
            @endif
        @else
            @include('app._today-open-work-table', ['items' => $workspace->overdueAndBlocked->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Milestone sắp tới</h2>
        @if ($workspace->upcomingMilestones->items === [])
            @if ($workspace->upcomingMilestones->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Không có milestone nào sắp tới hoặc trễ cho các dự án bạn tham gia.</p>
            @endif
        @else
            @include('app._today-milestones-table', ['items' => $workspace->upcomingMilestones->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Thông báo chưa đọc</h2>
        @if ($workspace->unreadUpdates->items === [])
            @if ($workspace->unreadUpdates->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Không có thông báo chưa đọc.</p>
            @endif
        @else
            @include('app._today-notifications-list', ['items' => $workspace->unreadUpdates->items])
        @endif
    </section>

    @if ($workspace->teamException !== null)
        <section class="mt-6">
            @if ($workspace->teamException->availability === \App\Support\Dashboard\Availability::ERROR)
                <h3 class="mb-2 mt-6 text-base font-semibold">Khối lượng công việc đã ghi nhận</h3>
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @elseif ($workspace->teamException->items === [])
                <h3 class="mb-2 mt-6 text-base font-semibold">Khối lượng công việc đã ghi nhận</h3>
                <p class="text-sm text-slate-500">Không có thành viên nào có việc đang mở.</p>
            @else
                @include('app._today-team-exceptions', ['items' => $workspace->teamException->items])
            @endif
        </section>
    @endif
@endsection
