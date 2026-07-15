@extends('layouts.portal')

@section('title', 'Cổng khách hàng')

@section('content')
    <div class="space-y-6">
        <form method="POST" action="{{ route('portal.logout', ['tenantSlug' => $tenant->slug]) }}" class="text-right">
            @csrf
            <button type="submit" class="operator-button operator-button-secondary">Đăng xuất</button>
        </form>

        <x-ui.card title="Báo giá">
            @if ($quotes->isEmpty())
                <p class="text-sm text-slate-500">Chưa có báo giá.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase text-slate-500">
                                <th class="px-4 py-2">Số</th>
                                <th class="px-4 py-2">Tổng cộng</th>
                                <th class="px-4 py-2">Trạng thái</th>
                                <th class="px-4 py-2">Ngày gửi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($quotes as $quote)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('portal.quotes.show', ['tenantSlug' => $tenant->slug, 'id' => $quote->id]) }}" class="font-medium text-slate-900 hover:underline">{{ $quote->quote_number }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-right text-slate-700">{{ number_format((float) $quote->subtotal, 0, ',', '.') }}₫</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $statusLabels = [
                                                'sent' => 'Đã gửi',
                                                'accepted' => 'Đã chấp nhận',
                                                'rejected' => 'Từ chối',
                                                'revised' => 'Đã chỉnh sửa',
                                                'superseded' => 'Đã thay thế',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                            {{ $quote->status === 'sent' ? 'bg-amber-100 text-amber-800' :
                                               ($quote->status === 'accepted' ? 'bg-green-100 text-green-800' :
                                               ($quote->status === 'rejected' ? 'bg-red-100 text-red-800' :
                                               'bg-slate-100 text-slate-800')) }}">
                                            {{ $statusLabels[$quote->status] ?? $quote->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-500">{{ $quote->sent_at ? \Carbon\Carbon::parse($quote->sent_at)->format('d/m/Y') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Dự án">
            @if ($projects->isEmpty())
                <p class="text-sm text-slate-500">Chưa có dự án nào.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($projects as $project)
                        <li class="text-sm font-medium text-slate-900">{{ $project->name }} ({{ $project->code }})</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Tiến độ thiết kế">
            @if ($designItems->isEmpty())
                <p class="text-sm text-slate-500">Chưa có hạng mục thiết kế.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($designItems as $item)
                        <li class="text-sm">
                            <a href="{{ route('portal.design-items.show', ['tenantSlug' => $tenant->slug, 'id' => $item->id]) }}" class="font-medium text-slate-900 hover:underline">{{ $item->name }}</a>
                            — {{ $item->review_status }}
                            @if ($item->review_status === 'sent_to_client')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Chờ bạn phản hồi</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Tài liệu đã duyệt">
            @if ($documents->isEmpty())
                <p class="text-sm text-slate-500">Chưa có tài liệu.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($documents as $document)
                        <li class="text-sm text-slate-900">{{ $document->title ?? $document->name }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Hợp đồng">
            @if ($contracts->isEmpty())
                <p class="text-sm text-slate-500">Chưa có hợp đồng.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($contracts as $contract)
                        <x-ui.field-value :label="$contract->code" :value="number_format((float) $contract->total_value, 0, ',', '.') . ' ' . $contract->currency" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Công nợ">
            <x-ui.field-value label="Số dư còn lại" :value="number_format($outstandingBalance, 0, ',', '.') . '₫'" />
        </x-ui.card>
    </div>
@endsection
