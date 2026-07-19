<x-ui.data-table :headers="['Việc', 'Dự án', 'Loại', 'Hạn', 'Trạng thái']">
    @foreach ($items as $item)
        <tr>
            <td>
                <a href="{{ $item['url'] }}" class="operator-link font-medium">{{ $item['name'] }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $item['project_name'] }}</td>
            <td class="text-sm text-slate-600">{{ $item['kind_label'] }}</td>
            <td class="text-sm text-slate-600">{{ $item['end_date'] ? \Illuminate\Support\Carbon::parse($item['end_date'])->format('d/m/Y') : '—' }}</td>
            <td>
                @if ($item['is_overdue'])
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @elseif ($item['is_blocked'])
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Bị chặn</span>
                @else
                    <x-ui.status-badge :status="$item['status']" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
