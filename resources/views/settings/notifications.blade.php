@extends('layouts.operator')

@section('title', 'Cài đặt thông báo')
@section('page_title', 'Cài đặt thông báo')

@section('content')
    {{-- GAP-124 S-1: the company's Slack channel for business notifications. --}}
    <x-ui.page-header title="Cài đặt thông báo" description="Gửi các thông báo nghiệp vụ quan trọng vào kênh Slack của công ty." />

    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    <x-ui.card title="Slack">
        <div class="space-y-4 p-4 text-sm">
            <p>
                Trạng thái:
                @if ($slack['configured'] && $slack['enabled'])
                    <strong class="text-emerald-700" data-testid="slack-status">Đang bật</strong> (link {{ $slack['masked'] }})
                @elseif ($slack['configured'])
                    <strong class="text-amber-700" data-testid="slack-status">Đã lưu link, đang tắt</strong> (link {{ $slack['masked'] }})
                @else
                    <strong class="text-slate-600" data-testid="slack-status">Chưa kết nối</strong>
                @endif
            </p>
            <p class="text-slate-600">
                Gửi vào kênh: yêu cầu thay đổi đã áp dụng; hồ sơ trình duyệt (nộp, nộp lại, duyệt, từ chối); phiếu không đạt (NCR) mới;
                chứng chỉ thanh toán đã duyệt; nhắc việc cuối tháng; tóm tắt việc quá hạn lúc 8:00 mỗi sáng. Mỗi sự kiện một tin.
            </p>
            <form method="POST" action="{{ route('app.settings.notifications.update') }}" class="grid max-w-2xl gap-3" data-testid="slack-settings-form">
                @csrf
                <label class="grid gap-1">Đường link Slack (Incoming Webhook)
                    <input type="url" name="slack_webhook_url" class="operator-input" maxlength="500" autocomplete="off"
                           placeholder="{{ $slack['configured'] ? 'Để trống để giữ link đã lưu' : 'https://hooks.slack.com/services/…' }}">
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="slack_enabled" value="0">
                    <input type="checkbox" name="slack_enabled" value="1" @checked($slack['enabled'] || ! $slack['configured'])> Bật gửi vào Slack
                </label>
                @if ($slack['configured'])
                    <label class="inline-flex items-center gap-2 text-red-700">
                        <input type="checkbox" name="slack_remove" value="1"> Gỡ kênh Slack (xoá link đã lưu)
                    </label>
                @endif
                <div><button type="submit" class="operator-button operator-button-primary">Lưu</button></div>
            </form>
            @if ($slack['configured'])
                <form method="POST" action="{{ route('app.settings.notifications.test') }}">
                    @csrf
                    <button type="submit" class="operator-button operator-button-secondary">Gửi thử</button>
                </form>
            @endif
            <p class="text-xs text-slate-500">Cách lấy link: trong Slack, tạo ứng dụng → Incoming Webhooks → chọn kênh → sao chép "Webhook URL". Link được lưu mã hoá và không hiện lại đầy đủ.</p>
        </div>
    </x-ui.card>
@endsection
