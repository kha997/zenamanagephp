@extends('layouts.operator')

@section('title', 'Thư viện biểu mẫu')
@section('page_title', 'Thư viện biểu mẫu')

@section('content')
    <x-ui.page-header
        title="Thư viện biểu mẫu"
        description="Quản lý mẫu biểu mẫu HTML với placeholder dữ liệu."
    >
        <x-ui.button-link :href="route('operator.document-templates.create')" variant="primary">Tạo biểu mẫu mới</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        @if ($templates->isEmpty())
            <x-ui.empty-state
                title="Chưa có biểu mẫu"
                description="Tạo biểu mẫu đầu tiên để sử dụng trong hợp đồng, chứng chỉ hoặc dự án."
            >
                <x-ui.button-link :href="route('operator.document-templates.create')">Tạo biểu mẫu</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Tên biểu mẫu', 'Ngữ cảnh', 'Trạng thái', 'Phiên bản', 'Thao tác']">
                @foreach ($templates as $template)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $template->name }}</td>
                        <td>
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                {{ $contextLabels[$template->context] ?? $template->context }}
                            </span>
                        </td>
                        <td>
                            @if ($template->status === 'published')
                                <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Đã xuất bản</span>
                            @elseif ($template->status === 'draft')
                                <span class="rounded bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Nháp</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $template->status }}</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">
                            @if ($template->latestPublishedVersion)
                                v{{ $template->latestPublishedVersion->semver }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('operator.document-templates.edit', $template->id) }}" class="operator-link text-sm">Chỉnh sửa</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            <div class="px-4 py-3">
                {{ $templates->links() }}
            </div>
        @endif
    </x-ui.card>
@endsection
