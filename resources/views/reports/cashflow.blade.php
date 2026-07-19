@extends('layouts.operator')

@section('title', 'Dòng tiền')
@section('page_title', 'Dòng tiền')

@section('content')
    <x-ui.page-header
        title="Dòng tiền"
        description="Số liệu tiền thực thu/chi theo hợp đồng — khác với doanh số ghi nhận (KPI). lũy kế trong kỳ hiển thị."
    />

    <x-ui.card>
        @unless ($hasAny)
            <p class="mb-3 text-sm text-slate-500">Chưa có giao dịch nào được ghi nhận.</p>
        @endunless

        <x-ui.data-table :headers="['Tháng', 'Thu thực', 'Chi thực', 'Ròng', 'Lũy kế', 'Chờ thu']">
            @foreach ($rows as $row)
                <tr @class(['bg-slate-50 font-medium' => $row['month'] === $currentMonth])>
                    <td class="text-sm text-slate-700">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $row['month'])->format('m/Y') }}</td>
                    <td class="text-sm text-slate-700">{{ number_format($row['thu'], 0, ',', '.') }}</td>
                    <td class="text-sm text-slate-700">{{ number_format($row['chi'], 0, ',', '.') }}</td>
                    <td class="text-sm {{ $row['net'] < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ number_format($row['net'], 0, ',', '.') }}</td>
                    <td class="text-sm {{ $row['cumulative'] < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ number_format($row['cumulative'], 0, ',', '.') }}</td>
                    <td class="text-sm text-slate-700">{{ number_format($row['cho_thu'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </x-ui.card>
@endsection
