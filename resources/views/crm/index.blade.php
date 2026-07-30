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
            <x-ui.card>
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $column['label'] }}</span>
                    <span class="text-sm text-slate-500">{{ $column['count'] }} · {{ number_format($column['total_fee'], 0, ',', '.') }}₫</span>
                </div>

                @if ($column['items']->isEmpty())
                    <p class="text-sm text-slate-400">Trống</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($column['items'] as $opportunity)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
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
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>
@endsection
