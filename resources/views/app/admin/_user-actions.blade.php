<div class="flex flex-wrap gap-2">
    @if ((string) $user->id !== $actorId)
        @if ($user->is_active)
            <form method="POST" action="{{ route('app.admin.users.deactivate', $user->id) }}"
                  onsubmit="return confirm('Khoá tài khoản {{ $user->email }}? Người này sẽ bị đăng xuất ngay.')">
                @csrf
                <button type="submit" class="operator-button operator-button-secondary">Khoá</button>
            </form>
        @else
            <form method="POST" action="{{ route('app.admin.users.reactivate', $user->id) }}">
                @csrf
                <button type="submit" class="operator-button operator-button-secondary">Mở khoá</button>
            </form>
        @endif
    @endif
    <form method="POST" action="{{ route('app.admin.users.reset-password', $user->id) }}"
          onsubmit="return confirm('Cấp mật khẩu mới cho {{ $user->email }}? Mật khẩu cũ sẽ không dùng được nữa.')">
        @csrf
        <button type="submit" class="operator-button operator-button-secondary">Cấp lại mật khẩu</button>
    </form>
</div>
