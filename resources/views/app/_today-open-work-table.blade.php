<x-ui.data-table :headers="['Việc', 'Dự án', 'Loại', 'Hạn', 'Trạng thái']">
    @foreach ($items as $item)
        <tr>
            <td>
                <a href="{{ $item->url }}" class="operator-link font-medium">{{ $item->name }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $item->projectName }}</td>
            <td class="text-sm text-slate-600">{{ $item->kindLabel }}</td>
            <td class="text-sm text-slate-600">{{ $item->endDate ? $item->endDate->format('d/m/Y') : '—' }}</td>
            <td>
                @if ($item->isOverdue)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @elseif ($item->isBlocked)
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Bị chặn</span>
                @else
                    <x-ui.status-badge :status="$item->status" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
