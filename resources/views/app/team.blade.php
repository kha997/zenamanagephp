@extends('layouts.operator')

@section('title', 'Nhóm')
@section('page_title', 'Nhóm')

@section('content')
    <x-ui.page-header
        title="Thành viên tenant"
        description="Danh sách người dùng thuộc tenant hiện tại."
    >
        <x-ui.button-link :href="route('app.team.invite')" variant="secondary">Mời thành viên</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <x-ui.data-table :headers="['Tên', 'Email', 'Vai trò', 'Trạng thái', 'Tham gia']">
            @foreach ($users as $member)
                <tr>
                    <td class="font-medium text-slate-900">{{ $member->name }}</td>
                    <td class="text-sm text-slate-600">{{ $member->email }}</td>
                    <td class="text-sm text-slate-600">{{ $member->role ?? '—' }}</td>
                    <td><x-ui.status-badge :status="$member->is_active ? 'active' : 'inactive'" /></td>
                    <td class="text-sm text-slate-600">{{ optional($member->created_at)->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </x-ui.card>
@endsection
