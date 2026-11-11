@extends('layouts.operator')

@section('title', 'Mẫu công việc')
@section('page_title', 'Mẫu công việc')

@section('content')
    {{-- GAP-123: list of the company's work templates. --}}
    <x-ui.page-header title="Mẫu công việc" description="Quy trình chuẩn để áp dụng cho dự án mới. Chỉ bản đã phát hành mới hiện ở trang dự án.">
        @if ($canEdit)
            <x-ui.button-link :href="route('app.work-templates.create')">+ Tạo mẫu</x-ui.button-link>
        @endif
    </x-ui.page-header>


    <x-ui.card>
        @if ($templates->isEmpty())
            <x-ui.empty-state title="Chưa có mẫu công việc" description="Tạo mẫu đầu tiên để áp dụng cho dự án." />
        @else
            <x-ui.data-table :headers="['Tên mẫu', 'Mã', 'Trạng thái', 'Phiên bản phát hành', '']">
                @foreach ($templates as $template)
                    @php
                        $published = $template->versions->whereNotNull('published_at')->sortByDesc('published_at')->first();
                        $hasDraft = $template->versions->whereNull('published_at')->isNotEmpty();
                    @endphp
                    <tr data-testid="work-template-row">
                        <td class="text-sm font-semibold">{{ $template->name }}</td>
                        <td class="text-xs text-slate-600">{{ $template->code }}</td>
                        <td class="text-sm">
                            @if ($published)<span class="rounded bg-emerald-100 px-1 text-xs text-emerald-800">Đã phát hành</span>@endif
                            @if ($hasDraft)<span class="rounded bg-amber-100 px-1 text-xs text-amber-800">Có bản nháp</span>@endif
                        </td>
                        <td class="text-sm">{{ $published ? $published->semver . ' · ' . $published->published_at?->format('d/m/Y') : '—' }}</td>
                        <td class="text-right">
                            @if ($canEdit)
                                <a href="{{ route('app.work-templates.edit', $template->id) }}" class="operator-link text-sm">Soạn</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection
