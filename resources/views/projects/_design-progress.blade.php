{{-- Khối "Thiết kế & tiến độ" — dùng chung cho trang dự án và trang hợp đồng thiết kế.
     Nhận: $designItems (collection), $blockedItems (collection map type/name/note/blocked_at),
     $tasks (nullable — chỉ render khối Công việc khi được truyền). --}}
<x-ui.card title="Thiết kế & tiến độ">
    @if ($blockedItems->isNotEmpty())
        <div class="mb-4 rounded border border-red-200 bg-red-50 p-3">
            <div class="mb-1 font-medium text-red-700">Đang vướng ({{ $blockedItems->count() }})</div>
            <ul class="space-y-1 text-sm text-red-800">
                @foreach ($blockedItems as $blocked)
                    <li>{{ $blocked['type'] }} — {{ $blocked['name'] }}: {{ $blocked['note'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h3 class="mb-2 text-sm font-semibold text-slate-700">Hạng mục thiết kế</h3>
    @forelse ($designItems as $designItem)
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
            <a href="{{ route('operator.design-items.show', $designItem->id) }}" class="font-medium">{{ $designItem->name }}</a>
            <x-ui.status-badge :status="$designItem->review_status" />
            @if ($designItem->revision_count > 0)
                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Sửa lần {{ $designItem->revision_count }}</span>
            @endif
            @if ($designItem->blocked_at)
                <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Vướng</span>
            @endif
            <span class="text-slate-500">{{ $designItem->assignee?->name ?? 'Chưa giao' }}</span>
            @if ($designItem->due_to_client_at)
                <span class="text-slate-400">hạn gửi khách {{ $designItem->due_to_client_at->format('d/m/Y') }}</span>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-500">Chưa có hạng mục thiết kế.</p>
    @endforelse

    @if (($tasks ?? null) !== null)
        <h3 class="mb-2 mt-4 text-sm font-semibold text-slate-700">Công việc</h3>
        @forelse ($tasks as $task)
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                <span class="font-medium">{{ $task->title ?? $task->name }}</span>
                <x-ui.status-badge :status="$task->status" />
                @if ($task->blocked_at)
                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Vướng</span>
                @endif
                <span class="text-slate-500">{{ $task->assignee?->name ?? 'Chưa giao' }}</span>
                <span class="text-slate-400">{{ (int) $task->progress_percent }}%</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Chưa có công việc.</p>
        @endforelse
    @endif
</x-ui.card>
