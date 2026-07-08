@extends('layouts.operator')

@section('title', 'Templates')
@section('page_title', 'Templates')

@section('content')
    <x-ui.page-header
        title="Templates"
        description="Mẫu quy trình/công việc dùng lại giữa các dự án."
    >
        <x-ui.button-link href="/app/templates/builder" variant="secondary">Trình dựng template</x-ui.button-link>
        <x-ui.button-link href="/app/templates/create">Tạo template</x-ui.button-link>
    </x-ui.page-header>

    @if ($templates->isEmpty())
        <x-ui.empty-state
            title="Chưa có template"
            description="Tạo template đầu tiên để chuẩn hóa quy trình giữa các dự án."
        />
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Tên template', 'Nhóm', 'Phiên bản', 'Trạng thái', 'Cập nhật']">
                @foreach ($templates as $template)
                    <tr>
                        <td>
                            <a href="/app/templates/{{ $template->id }}" class="operator-link font-medium">{{ $template->template_name }}</a>
                        </td>
                        <td class="text-sm text-slate-600">{{ $template->category ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $template->version ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$template->is_active ? 'active' : 'inactive'" /></td>
                        <td class="text-sm text-slate-600">{{ optional($template->updated_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection
