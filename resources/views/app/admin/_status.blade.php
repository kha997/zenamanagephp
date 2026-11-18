@if ($user->is_active)
    <span class="text-emerald-700" data-status="active">Đang hoạt động</span>
@else
    <span class="text-red-700" data-status="inactive">Đã khoá</span>
@endif
