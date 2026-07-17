@extends('layouts.operator')

@section('title', 'Tri thức nội bộ')
@section('page_title', 'Tri thức nội bộ')

@section('content')
    <x-ui.page-header
        title="Tri thức nội bộ"
        description="SOP, checklist và bài học công trình dùng chung toàn công ty."
    >
        <x-ui.button-link :href="route('operator.knowledge.create')" variant="primary">Viết bài mới</x-ui.button-link>
    </x-ui.page-header>

    <form method="GET" action="{{ route('operator.knowledge.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo tiêu đề..." class="operator-input" />
        <select name="type" class="operator-input">
            <option value="">Tất cả loại</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                    {{ ['sop' => 'Quy trình chuẩn (SOP)', 'checklist' => 'Checklist', 'lesson_learned' => 'Bài học công trình'][$type] ?? $type }}
                </option>
            @endforeach
        </select>
        <input type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="Nhóm ngành..." class="operator-input" />
        <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
    </form>

    <x-ui.card>
        @if ($articles->isEmpty())
            <x-ui.empty-state
                title="Chưa có bài viết"
                description="Viết bài SOP, checklist hoặc bài học công trình đầu tiên."
            >
                <x-ui.button-link :href="route('operator.knowledge.create')">Viết bài mới</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Tiêu đề', 'Loại', 'Nhóm ngành', 'Trạng thái', 'Cập nhật']">
                @foreach ($articles as $article)
                    <tr>
                        <td class="font-medium text-slate-900">
                            <a href="{{ route('operator.knowledge.show', $article->id) }}" class="operator-link">{{ $article->title }}</a>
                        </td>
                        <td>{{ $article->typeLabel() }}</td>
                        <td class="text-sm text-slate-600">{{ $article->category ?? '—' }}</td>
                        <td>
                            @if ($article->status === 'published')
                                <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Đã xuất bản</span>
                            @else
                                <span class="rounded bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Nháp</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">{{ $article->updated_at->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            <div class="px-4 py-3">
                {{ $articles->links() }}
            </div>
        @endif
    </x-ui.card>
@endsection
