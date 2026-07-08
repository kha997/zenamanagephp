@extends('layouts.operator')

@section('title', 'Chi tiết nhật ký công trường')
@section('page_title', 'Chi tiết nhật ký công trường')

@section('content')
    <x-ui.page-header
        title="Nhật ký ngày {{ optional($siteDiary->diary_date)->format('d/m/Y') }}"
        description="{{ $siteDiary->project?->name ?? '' }}{{ $siteDiary->project?->code ? ' (' . $siteDiary->project->code . ')' : '' }}"
    >
        <x-ui.button-link :href="route('operator.site-diaries.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin chung">
        <div class="operator-form-grid">
            <x-ui.field-value label="Mã nhật ký" :value="$siteDiary->diary_number" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$siteDiary->status" />
            </x-ui.field-value>
            <x-ui.field-value label="Thời tiết" :value="$siteDiary->weather ?? '—'" />
            <x-ui.field-value label="Nhiệt độ" :value="$siteDiary->temperature ?? '—'" />
            <x-ui.field-value label="Số nhân lực" :value="(string) $siteDiary->manpower_count" />
            <x-ui.field-value label="Người lập" :value="$siteDiary->creator?->name ?? '—'" />
            @if ($siteDiary->status === 'approved')
                <x-ui.field-value label="Người duyệt" :value="$siteDiary->approver?->name ?? '—'" />
                <x-ui.field-value label="Duyệt lúc" :value="optional($siteDiary->approved_at)->format('d/m/Y H:i') ?? '—'" />
            @endif
        </div>
    </x-ui.card>

    <x-ui.card title="Công việc thực hiện">
        <p class="whitespace-pre-line text-slate-700">{{ $siteDiary->work_performed }}</p>
    </x-ui.card>

    @if ($siteDiary->equipment_used)
        <x-ui.card title="Thiết bị sử dụng">
            <p class="whitespace-pre-line text-slate-700">{{ $siteDiary->equipment_used }}</p>
        </x-ui.card>
    @endif

    @if ($siteDiary->materials_delivered)
        <x-ui.card title="Vật tư nhập về">
            <p class="whitespace-pre-line text-slate-700">{{ $siteDiary->materials_delivered }}</p>
        </x-ui.card>
    @endif

    @if ($siteDiary->safety_notes)
        <x-ui.card title="Ghi chú an toàn">
            <p class="whitespace-pre-line text-slate-700">{{ $siteDiary->safety_notes }}</p>
        </x-ui.card>
    @endif

    @if ($siteDiary->visitors)
        <x-ui.card title="Khách / đoàn kiểm tra">
            <p class="whitespace-pre-line text-slate-700">{{ $siteDiary->visitors }}</p>
        </x-ui.card>
    @endif

    @if ($siteDiary->delays_issues)
        <x-ui.card title="Chậm trễ / sự cố">
            <p class="whitespace-pre-line text-slate-700">{{ $siteDiary->delays_issues }}</p>
        </x-ui.card>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        @if ($siteDiary->status === 'draft')
            <form method="POST" action="{{ route('operator.site-diaries.submit', $siteDiary->id) }}">
                @csrf
                <button type="submit" class="operator-button operator-button-primary">Gửi duyệt</button>
            </form>
        @endif

        @if ($siteDiary->status === 'submitted')
            <form method="POST" action="{{ route('operator.site-diaries.approve', $siteDiary->id) }}">
                @csrf
                <button type="submit" class="operator-button operator-button-primary">Phê duyệt</button>
            </form>
        @endif
    </div>
@endsection
