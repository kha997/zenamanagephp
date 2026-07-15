@extends('layouts.portal')

@section('title', $quote->quote_number . ' — Báo giá')

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
                    <h2 class="text-lg font-semibold text-slate-900">{{ $quote->quote_number }}</h2>
                    @if ($quote->opportunity)
                        <p class="text-sm text-slate-500">{{ $quote->opportunity->opportunity_name }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-600">Trạng thái:</span>
                    @php
                        $statusLabels = [
                            'draft' => 'Nháp',
                            'sent' => 'Đã gửi',
                            'accepted' => 'Đã chấp nhận',
                            'rejected' => 'Từ chối',
                            'revised' => 'Đã chỉnh sửa',
                        ];
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $quote->status === 'sent' ? 'bg-amber-100 text-amber-800' :
                           ($quote->status === 'accepted' ? 'bg-green-100 text-green-800' :
                           ($quote->status === 'rejected' ? 'bg-red-100 text-red-800' :
                           'bg-slate-100 text-slate-800')) }}">
                        {{ $statusLabels[$quote->status] ?? $quote->status }}
                    </span>
                </div>

                @if ($quote->valid_until)
                    <div class="text-sm text-slate-600">
                        Hiệu lực đến: <span class="font-medium text-slate-900">{{ \Carbon\Carbon::parse($quote->valid_until)->format('d/m/Y') }}</span>
                    </div>
                @endif
            </div>
        </x-ui.card>

        @if ($quote->lines->count())
            <x-ui.card title="Chi tiết báo giá">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase text-slate-500">
                                <th class="px-4 py-2">STT</th>
                                <th class="px-4 py-2">Hạng mục</th>
                                <th class="px-4 py-2 text-right">Đơn giá</th>
                                <th class="px-4 py-2 text-right">KL</th>
                                <th class="px-4 py-2 text-right">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($quote->lines as $line)
                                <tr>
                                    <td class="px-4 py-2 text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-2 text-slate-900 font-medium">{{ $line->name }}</td>
                                    <td class="px-4 py-2 text-right text-slate-700">{{ number_format((float) $line->unit_price, 0, ',', '.') }}₫</td>
                                    <td class="px-4 py-2 text-right text-slate-700">{{ $line->quantity }}</td>
                                    <td class="px-4 py-2 text-right text-slate-900 font-medium">{{ number_format((float) $line->amount, 0, ',', '.') }}₫</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 font-semibold">
                                <td colspan="4" class="px-4 py-2 text-right text-slate-900">Tổng cộng</td>
                                <td class="px-4 py-2 text-right text-slate-900">{{ number_format((float) $quote->subtotal, 0, ',', '.') }}₫</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-sm text-slate-600 italic">Bằng chữ: {{ $amountInWords }}</p>
            </x-ui.card>
        @endif

        @if ($quote->note)
            <x-ui.card title="Ghi chú">
                <p class="text-sm text-slate-700">{{ $quote->note }}</p>
            </x-ui.card>
        @endif

        @if ($quote->status === 'sent')
            <x-ui.card title="Phản hồi của bạn">
                <div class="space-y-4">
                    <form method="POST" action="{{ route('portal.quotes.accept', ['tenantSlug' => $tenant->slug, 'id' => $quote->id]) }}" onsubmit="return confirm('Xác nhận CHẤP NHẬN báo giá này? Hành động có giá trị xác nhận chính thức.')">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Chấp nhận báo giá</button>
                    </form>

                    <hr class="border-slate-200">

                    <form method="POST" action="{{ route('portal.quotes.reject', ['tenantSlug' => $tenant->slug, 'id' => $quote->id]) }}" onsubmit="return confirm('Xác nhận TỪ CHỐI báo giá này?')">
                        @csrf
                        <div class="space-y-2">
                            <label for="note" class="block text-sm font-medium text-slate-700">Lý do từ chối (tùy chọn)</label>
                            <textarea name="note" id="note" rows="3" maxlength="2000" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Nhập lý do nếu muốn..."></textarea>
                        </div>
                        <button type="submit" class="mt-3 operator-button operator-button-secondary">Từ chối báo giá</button>
                    </form>
                </div>
            </x-ui.card>
        @elseif ($quote->status === 'accepted')
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">Bạn đã chấp nhận báo giá này.</div>
        @elseif ($quote->status === 'rejected')
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">Bạn đã từ chối báo giá này.</div>
        @else
            <div class="rounded-md bg-slate-50 p-4 text-sm text-slate-600">Báo giá đang được xử lý.</div>
        @endif
    </div>
@endsection
