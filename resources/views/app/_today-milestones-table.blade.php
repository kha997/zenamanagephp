<x-ui.data-table :headers="['Milestone', 'Dự án', 'Ngày mục tiêu', 'Trạng thái']">
    @foreach ($items as $milestone)
        <tr>
            <td class="font-medium">{{ $milestone->name }}</td>
            <td>
                <a href="{{ $milestone->url }}" class="operator-link">{{ $milestone->projectName }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $milestone->targetDate?->format('d/m/Y') ?? '—' }}</td>
            <td>
                @if ($milestone->isOverdue)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @else
                    <x-ui.status-badge :status="$milestone->status" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
