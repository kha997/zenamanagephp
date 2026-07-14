@extends('layouts.portal')

@section('title', $item->name . ' — Cổng khách hàng')

@section('content')
    <div class="space-y-6">
        <form method="POST" action="{{ route('portal.logout', ['tenantSlug' => $tenant->slug]) }}" class="text-right">
            @csrf
            <button type="submit" class="operator-button operator-button-secondary">Đăng xuất</button>
        </form>

        @if (session('success'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.card>
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $item->name }}</h2>
                    @if ($item->project)
                        <p class="text-sm text-slate-500">{{ $item->project->name }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-600">Trạng thái:</span>
                    @php
                        $statusLabels = [
                            'draft' => 'Nháp',
                            'internal_review' => 'Đang thẩm định',
                            'sent_to_client' => 'Đã gửi khách',
                            'revision_requested' => 'Yêu cầu chỉnh sửa',
                            'approved' => 'Đã duyệt',
                            'final' => 'Hoàn thành',
                        ];
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $item->review_status === 'sent_to_client' ? 'bg-amber-100 text-amber-800' :
                           ($item->review_status === 'approved' ? 'bg-green-100 text-green-800' :
                           ($item->review_status === 'revision_requested' ? 'bg-red-100 text-red-800' :
                           'bg-slate-100 text-slate-800')) }}">
                        {{ $statusLabels[$item->review_status] ?? $item->review_status }}
                    </span>
                </div>

                @if ($item->due_to_client_at)
                    <div class="text-sm text-slate-600">
                        Hạn phản hồi: <span class="font-medium text-slate-900">{{ $item->due_to_client_at->format('d/m/Y') }}</span>
                    </div>
                @endif
            </div>
        </x-ui.card>

        @if ($item->revisions->count())
            <x-ui.card title="Lịch sử chỉnh sửa">
                <ul class="space-y-3">
                    @foreach ($item->revisions->sortByDesc('revision_no') as $revision)
                        <li class="text-sm">
                            <span class="font-medium text-slate-900">Sửa lần {{ $revision->revision_no }}</span>
                            <span class="text-slate-500">— {{ $revision->created_at->format('d/m/Y H:i') }}</span>
                            @if ($revision->client_feedback)
                                <p class="mt-1 text-slate-700">{{ $revision->client_feedback }}</p>
                            @endif
                            <span class="text-xs {{ $revision->resolved_at ? 'text-green-600' : 'text-amber-600' }}">
                                {{ $revision->resolved_at ? 'Đã xử lý' : 'Đang xử lý' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($item->review_status === 'sent_to_client')
            <x-ui.card title="Phản hồi của bạn">
                <div class="space-y-4">
                    <form method="POST" action="{{ route('portal.design-items.approve', ['tenantSlug' => $tenant->slug, 'id' => $item->id]) }}" onsubmit="return confirm('Xác nhận DUYỆT phương án này? Hành động có giá trị xác nhận chính thức.')">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Duyệt phương án</button>
                    </form>

                    <hr class="border-slate-200">

                    <form method="POST" action="{{ route('portal.design-items.request-revision', ['tenantSlug' => $tenant->slug, 'id' => $item->id]) }}" onsubmit="return confirm('Xác nhận gửi yêu cầu chỉnh sửa?')">
                        @csrf
                        <div class="space-y-2">
                            <label for="client_feedback_notes" class="block text-sm font-medium text-slate-700">Nội dung chỉnh sửa</label>
                            <textarea name="client_feedback_notes" id="client_feedback_notes" rows="4" required maxlength="2000" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Mô tả những điểm cần chỉnh sửa..."></textarea>
                        </div>
                        <button type="submit" class="mt-3 operator-button operator-button-secondary">Gửi yêu cầu chỉnh sửa</button>
                    </form>
                </div>
            </x-ui.card>
        @elseif ($item->review_status === 'approved')
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">Bạn đã duyệt phương án này.</div>
        @else
            <div class="rounded-md bg-slate-50 p-4 text-sm text-slate-600">Phương án đang được đội ngũ xử lý.</div>
        @endif
    </div>
@endsection
