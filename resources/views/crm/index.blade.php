@extends('layouts.operator')

@section('title', 'CRM — Pipeline')
@section('page_title', 'CRM — Pipeline')

@section('content')
    <x-ui.page-header
        title="Pipeline kinh doanh"
        description="Cơ hội theo giai đoạn — từ lead mới đến ký hợp đồng."
    >
        <x-ui.button-link :href="route('operator.crm.leads')" variant="secondary">
            Hộp lead @if($newLeadCount > 0)({{ $newLeadCount }} mới)@endif
        </x-ui.button-link>
        <x-ui.button-link :href="route('operator.crm.accounts')" variant="secondary">Khách hàng</x-ui.button-link>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($board as $groupKey => $column)
            <x-ui.card
                data-board-group="{{ $groupKey }}"
                data-column-label="{{ $column['label'] }}"
                data-requires-choice="{{ $column['requires_choice'] ? '1' : '0' }}"
                data-default-entry-stage="{{ $column['default_entry_stage'] ?? '' }}"
                data-choice-options="{{ json_encode($column['choice_options'] ?? []) }}"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $column['label'] }}</span>
                    <span class="text-sm text-slate-500">
                        <span data-column-count>{{ $column['count'] }}</span> ·
                        <span data-column-total>{{ number_format($column['total_fee'], 0, ',', '.') }}₫</span>
                    </span>
                </div>

                @if ($column['items']->isEmpty())
                    <p class="text-sm text-slate-400" data-column-empty>Trống</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($column['items'] as $opportunity)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                                data-opportunity-id="{{ $opportunity->id }}"
                                data-current-stage="{{ $opportunity->pipeline_stage }}"
                                data-terminal="{{ $opportunity->isTerminal() ? '1' : '0' }}"
                                data-amount="{{ (int) ($opportunity->estimated_fee ?? 0) }}"
                            >
                                <div class="flex items-start gap-2">
                                    @if (!$opportunity->isTerminal() && auth()->user()?->hasPermission('crm.manage'))
                                        <button type="button" class="crm-drag-handle" draggable="true" aria-label="Kéo để chuyển giai đoạn">⋮⋮</button>
                                    @endif
                                    <div class="flex-1">
                                        <a href="{{ route('operator.crm.opportunities.show', $opportunity->id) }}" class="operator-link font-medium">
                                            {{ $opportunity->opportunity_name }}
                                        </a>
                                        <div class="text-xs text-slate-500">
                                            {{ $opportunity->account?->display_name ?? '—' }}
                                            · {{ $opportunity->salesOwner?->name ?? 'Chưa gán' }}
                                            @if ($opportunity->estimated_fee)
                                                · {{ number_format((float) $opportunity->estimated_fee, 0, ',', '.') }}₫
                                            @endif
                                        </div>
                                        @if (!$opportunity->isTerminal() && auth()->user()?->hasPermission('crm.manage'))
                                            <button type="button" class="crm-stage-transition-btn text-xs operator-link">Chuyển giai đoạn</button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <dialog data-crm-stage-dialog>
        <form method="dialog">
            <p>Chuyển "<span data-dialog-opportunity-name></span>" sang:</p>

            <div data-dialog-group-picker>
                @foreach ($board as $groupKey => $column)
                    <button type="button" class="crm-dialog-group-option" data-group="{{ $groupKey }}">
                        {{ $column['label'] }}
                    </button>
                @endforeach
            </div>

            <div data-dialog-choice-picker class="hidden"></div>

            <textarea data-dialog-reason placeholder="Lý do (bắt buộc nếu chọn Thua)" class="hidden"></textarea>
            <button type="button" data-dialog-cancel>Hủy</button>
            <button type="button" data-dialog-confirm disabled>Xác nhận</button>
        </form>
    </dialog>
@endsection
