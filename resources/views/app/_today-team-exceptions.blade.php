<h3 class="mb-2 mt-6 text-base font-semibold">Khối lượng công việc đã ghi nhận</h3>
<x-ui.data-table :headers="['Thành viên', 'Đang mở', 'Quá hạn', 'Bị chặn']">
    @foreach ($items as $summary)
        <tr>
            <td class="font-medium">{{ $summary->userName }}</td>
            <td>{{ $summary->openCount }}</td>
            <td>{{ $summary->overdueCount }}</td>
            <td>{{ $summary->blockedCount }}</td>
        </tr>
    @endforeach
</x-ui.data-table>
<p class="mt-2 text-xs text-slate-500">Đây là số việc đã ghi nhận, không phải năng lực hay mức độ sẵn sàng.</p>
