@php($current = $user->roles->pluck('name')->first())
<form method="POST" action="{{ route('app.admin.users.role', $user->id) }}" class="flex flex-wrap items-center gap-2">
    @csrf
    <select name="role" class="operator-input" aria-label="Vai trò của {{ $user->email }}">
        @foreach ($roles as $name => $description)
            <option value="{{ $name }}" @selected($current === $name)>{{ $name }}</option>
        @endforeach
        @if ($current === null)
            <option value="" selected disabled>(chưa có vai trò)</option>
        @endif
    </select>
    <button type="submit" class="operator-button operator-button-secondary">Đổi</button>
</form>
