@extends('layouts.portal')

@section('title', 'Cổng khách hàng')

@section('content')
    <div class="space-y-6">
        <form method="POST" action="{{ route('portal.logout', ['tenantSlug' => $tenant->slug]) }}" class="text-right">
            @csrf
            <button type="submit" class="operator-button operator-button-secondary">Đăng xuất</button>
        </form>

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
                        <li class="text-sm"><span class="font-medium text-slate-900">{{ $item->name }}</span> — {{ $item->review_status }}</li>
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
