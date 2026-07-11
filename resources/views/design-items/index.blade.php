@extends('layouts.operator')

@section('title', 'Công việc thiết kế')
@section('page_title', 'Công việc thiết kế')

@section('content')
    <x-ui.page-header
        title="Công việc thiết kế"
        description="Theo dõi deliverable thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng."
    >
        <x-ui.button-link :href="route('operator.design-items.create')" variant="primary">Tạo công việc mới</x-ui.button-link>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($board as $label => $column)
            <x-ui.card>
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $label }}</span>
                    <span class="text-sm text-slate-500">{{ $column['count'] }}</span>
                </div>

                @if ($column['items']->isEmpty())
                    <p class="text-sm text-slate-400">Trống</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($column['items'] as $item)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <a href="{{ route('operator.design-items.show', $item->id) }}" class="operator-link font-medium">
                                    {{ $item->name }}
                                </a>
                                <div class="text-xs text-slate-500">
                                    {{ $item->project?->name ?? '—' }}
                                    · {{ $item->assignee?->name ?? 'Chưa gán' }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>
@endsection
