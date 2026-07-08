@extends('layouts.operator')

@section('title', 'Tìm kiếm')
@section('page_title', 'Tìm kiếm toàn hệ thống')

@section('content')
    <x-ui.page-header
        title="Tìm kiếm toàn hệ thống"
        description="Tìm theo mã hoặc tên trong tất cả các module: dự án, RFI, submittal, hợp đồng, vật tư, nhà cung cấp, NCR, nhật ký công trường."
    />

    <x-ui.card>
        <form method="GET" action="{{ route('operator.search.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="operator-field flex-1 min-w-64">
                <label for="q">Từ khóa</label>
                <input id="q" name="q" type="search" class="operator-input" value="{{ $term }}" placeholder="Nhập tối thiểu 2 ký tự..." autofocus>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tìm kiếm</button>
        </form>
    </x-ui.card>

    @if ($term !== '' && mb_strlen($term) < 2)
        <x-ui.empty-state title="Từ khóa quá ngắn" description="Vui lòng nhập tối thiểu 2 ký tự." />
    @elseif ($term !== '' && $totalCount === 0)
        <x-ui.empty-state
            title="Không tìm thấy kết quả"
            description="Không có bản ghi nào khớp với «{{ $term }}». Thử từ khóa khác hoặc kiểm tra chính tả."
        />
    @elseif ($totalCount > 0)
        <div class="mb-4 text-sm text-slate-500">{{ $totalCount }} kết quả cho «{{ $term }}»</div>

        @foreach ($results as $group => $items)
            <x-ui.card :title="$group . ' (' . count($items) . ')'">
                <x-ui.data-table :headers="['Tên', 'Mã', 'Trạng thái']">
                    @foreach ($items as $item)
                        <tr>
                            <td class="font-medium text-slate-900">
                                @if ($item['url'] !== '#')
                                    <a href="{{ $item['url'] }}" class="operator-link">{{ $item['title'] }}</a>
                                @else
                                    {{ $item['title'] }}
                                @endif
                            </td>
                            <td class="text-sm text-slate-600">{{ $item['subtitle'] ?: '—' }}</td>
                            <td>
                                @if ($item['status'])
                                    <x-ui.status-badge :status="$item['status']" />
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </x-ui.card>
        @endforeach
    @endif
@endsection
