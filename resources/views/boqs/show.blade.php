@extends('layouts.operator')

@section('title', 'BOQ ' . $boq->code)
@section('page_title', 'BOQ ' . $boq->code)

@section('content')
    <x-ui.page-header
        :title="'BOQ ' . $boq->code"
        :description="$boq->name"
    >
        <x-ui.button-link :href="route('operator.boqs.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    <div class="space-y-6">
        <x-ui.card title="Thông tin">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field-value label="Mã" :value="$boq->code" />
                <x-ui.field-value label="Dự án" :value="($boq->project?->name ?? '—') . ($boq->project?->code ? ' (' . $boq->project->code . ')' : '')" />
                <x-ui.field-value label="Ngày tạo" :value="optional($boq->created_at)->format('d/m/Y H:i')" />
            </div>
            @if ($boq->description)
                <div class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $boq->description }}</div>
            @endif
        </x-ui.card>

        <x-ui.card title="Hạng mục ({{ $boq->lineItems->count() }})">
            @if ($boq->lineItems->isEmpty())
                <div class="py-6 text-center text-sm text-slate-500">Chưa có hạng mục nào. Thêm hạng mục đầu tiên bên dưới.</div>
            @else
                <x-ui.data-table :headers="['Mã', 'Tên', 'Khối lượng', 'Đơn vị', 'Mô tả']">
                    @foreach ($boq->lineItems as $lineItem)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $lineItem->code ?? '—' }}</td>
                            <td class="font-medium text-slate-900">{{ $lineItem->name }}</td>
                            <td class="text-sm text-slate-600">{{ number_format((float) $lineItem->quantity, 2) }}</td>
                            <td class="text-sm text-slate-600">{{ $lineItem->unit ?? '—' }}</td>
                            <td class="text-sm text-slate-600">{{ $lineItem->description ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>

        <x-ui.card title="Thêm hạng mục">
            @if ($errors->any())
                <div class="operator-error-list">
                    <ul class="space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('operator.boqs.lines.store', $boq->id) }}" class="space-y-5">
                @csrf

                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="line_code">Mã hạng mục</label>
                        <input id="line_code" name="code" type="text" class="operator-input" value="{{ old('code') }}" maxlength="100">
                    </div>

                    <div class="operator-field">
                        <label for="line_name">Tên <span class="text-rose-600">*</span></label>
                        <input id="line_name" name="name" type="text" class="operator-input" value="{{ old('name') }}" maxlength="255" required>
                    </div>

                    <div class="operator-field">
                        <label for="quantity">Khối lượng <span class="text-rose-600">*</span></label>
                        <input id="quantity" name="quantity" type="number" step="0.01" min="0" class="operator-input" value="{{ old('quantity') }}" required>
                    </div>

                    <div class="operator-field">
                        <label for="unit">Đơn vị</label>
                        <input id="unit" name="unit" type="text" class="operator-input" value="{{ old('unit') }}" maxlength="50" placeholder="VD: m3, kg, cái">
                    </div>
                </div>

                <div class="operator-field">
                    <label for="line_description">Mô tả</label>
                    <textarea id="line_description" name="description" class="operator-textarea">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="operator-button operator-button-primary">Thêm hạng mục</button>
            </form>
        </x-ui.card>
    </div>
@endsection
