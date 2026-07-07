@extends('layouts.operator')

@section('title', 'Bảng điều hành')
@section('page_title', 'Bảng điều hành')

@section('content')
    <x-ui.page-header
        title="Bảng điều hành"
        description="Tổng quan luồng công việc mua sắm, hồ sơ và thay đổi của dự án."
    />

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.card title="Yêu cầu vật tư">
            <div class="text-4xl font-semibold text-slate-900">{{ $requestCount }}</div>
            <div class="mt-4">
                <x-ui.button-link :href="route('operator.material-requests.index')" variant="inline">Mở danh sách →</x-ui.button-link>
            </div>
        </x-ui.card>

        <x-ui.card title="Phiếu nhập">
            <div class="text-4xl font-semibold text-slate-900">{{ $receiptCount }}</div>
            <div class="mt-4">
                <x-ui.button-link :href="route('operator.receipts.index')" variant="inline">Mở danh sách →</x-ui.button-link>
            </div>
        </x-ui.card>

        <x-ui.card title="RFI đang mở">
            <div class="flex items-baseline gap-3">
                <div class="text-4xl font-semibold text-slate-900">{{ $rfiOpenCount }}</div>
                @if ($rfiOverdueCount > 0)
                    <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">{{ $rfiOverdueCount }} quá hạn</span>
                @endif
            </div>
            <div class="mt-4">
                <x-ui.button-link :href="route('operator.rfis.index', ['status' => 'open'])" variant="inline">Mở danh sách →</x-ui.button-link>
            </div>
        </x-ui.card>

        <x-ui.card title="Chờ xét duyệt">
            <div class="text-4xl font-semibold text-slate-900">{{ $submittalPendingCount + $changeRequestPendingCount }}</div>
            <div class="mt-2 text-sm text-slate-500">
                {{ $submittalPendingCount }} submittal · {{ $changeRequestPendingCount }} thay đổi
            </div>
            <div class="mt-4 flex gap-4">
                <x-ui.button-link :href="route('operator.submittals.index', ['status' => 'submitted'])" variant="inline">Submittals →</x-ui.button-link>
                <x-ui.button-link :href="route('operator.change-requests.index', ['status' => 'submitted'])" variant="inline">CRs →</x-ui.button-link>
            </div>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-3">
        <x-ui.card title="Kiểm định đã lên lịch">
            <div class="text-4xl font-semibold text-slate-900">{{ $inspectionScheduledCount }}</div>
            <div class="mt-4">
                <x-ui.button-link :href="route('operator.inspections.index', ['status' => 'scheduled'])" variant="inline">Mở danh sách →</x-ui.button-link>
            </div>
        </x-ui.card>

        <x-ui.card title="NCR đang mở">
            <div class="flex items-baseline gap-3">
                <div class="text-4xl font-semibold {{ $ncrOpenCount > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $ncrOpenCount }}</div>
                @if ($ncrOpenCount > 0)
                    <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">cần xử lý</span>
                @endif
            </div>
            <div class="mt-4">
                <x-ui.button-link :href="route('operator.inspections.index')" variant="inline">Mở kiểm định →</x-ui.button-link>
            </div>
        </x-ui.card>

        <x-ui.card title="Hợp đồng hiệu lực">
            <div class="text-4xl font-semibold text-slate-900">{{ $contractActiveCount }}</div>
            <div class="mt-4">
                <x-ui.button-link :href="route('operator.contracts.index', ['status' => 'active'])" variant="inline">Mở danh sách →</x-ui.button-link>
            </div>
        </x-ui.card>
    </div>

    <div class="mt-6">
        <x-ui.card title="RFI gần đây">
            @if ($recentRfis->isEmpty())
                <div class="py-6 text-center text-sm text-slate-500">Chưa có RFI nào.</div>
            @else
                <x-ui.data-table :headers="['Số RFI', 'Tiêu đề', 'Dự án', 'Trạng thái', 'Ngày tạo']">
                    @foreach ($recentRfis as $rfi)
                        <tr>
                            <td>
                                <a href="{{ route('operator.rfis.show', $rfi->id) }}" class="font-semibold text-teal-700 hover:underline">{{ $rfi->rfi_number }}</a>
                            </td>
                            <td class="font-medium text-slate-900">{{ $rfi->title ?? $rfi->subject }}</td>
                            <td class="text-sm text-slate-600">{{ $rfi->project?->name ?? '—' }}</td>
                            <td><x-ui.status-badge :status="$rfi->status" /></td>
                            <td class="text-sm text-slate-600">{{ optional($rfi->created_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>
    </div>
@endsection
