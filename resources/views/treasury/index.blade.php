@extends('layouts.operator')

@section('title', 'Ngân quỹ')
@section('page_title', 'Ngân quỹ dự án')

@section('content')
    <x-ui.page-header
        title="Ngân quỹ dự án"
        description="Ví và đối tác tài chính theo từng dự án. Ghi nhận thu, chi và số dư sẽ có ở bước tiếp theo."
    >
        @can('treasury.manage-parties')
            <x-ui.button-link :href="route('operator.treasury.parties.index')" variant="secondary">Đối tác tài chính</x-ui.button-link>
        @endcan
    </x-ui.page-header>

    <x-ui.card>
        @if ($projects->isEmpty())
            <x-ui.empty-state
                title="Chưa có dự án nào"
                description="Bạn chỉ thấy ngân quỹ của các dự án mình là thành viên."
            />
        @else
            <x-ui.data-table :headers="['Mã', 'Dự án', 'Thao tác']">
                @foreach ($projects as $project)
                    <tr data-testid="treasury-project-row">
                        <td class="font-semibold text-slate-900">{{ $project->code }}</td>
                        <td class="font-medium text-slate-900">{{ $project->name }}</td>
                        <td>
                            <a href="{{ route('operator.treasury.projects.show', $project->id) }}" class="operator-button operator-button-inline">Mở ngân quỹ</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($projects->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $projects->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
