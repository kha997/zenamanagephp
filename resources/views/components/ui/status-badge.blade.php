@props(['status'])

@php
    $value = strtolower((string) $status);
    $classes = match ($value) {
        'draft' => 'bg-slate-100 text-slate-700',
        'submitted', 'open', 'pending_review', 'issued' => 'bg-amber-100 text-amber-800',
        'approved', 'fulfilled', 'answered', 'applied', 'accepted' => 'bg-emerald-100 text-emerald-800',
        'rejected', 'escalated' => 'bg-rose-100 text-rose-800',
        'closed', 'superseded' => 'bg-slate-200 text-slate-600',
        default => 'bg-slate-100 text-slate-700',
    };
    $label = match ($value) {
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'approved' => 'Đã phê duyệt',
        'fulfilled' => 'Hoàn tất',
        'rejected' => 'Từ chối',
        'open' => 'Đang mở',
        'answered' => 'Đã trả lời',
        'closed' => 'Đã đóng',
        'escalated' => 'Đã chuyển cấp',
        'pending_review' => 'Đang xét',
        'applied' => 'Đã áp dụng',
        'issued' => 'Đã phát hành',
        'accepted' => 'Đã chấp nhận',
        'superseded' => 'Đã thay thế',
        default => (string) $status,
    };
@endphp

<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $classes }}">
    {{ $label }}
</span>
