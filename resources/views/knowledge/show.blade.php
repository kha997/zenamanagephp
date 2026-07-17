@extends('layouts.operator')

@section('title', $article->title)
@section('page_title', $article->title)

@section('content')
    <x-ui.page-header :title="$article->title" :description="$article->typeLabel()">
        @if ($article->status === 'draft')
            <x-ui.button-link :href="route('operator.knowledge.edit', $article->id)">Sửa</x-ui.button-link>
        @endif
    </x-ui.page-header>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap gap-4 text-sm text-slate-600 mb-4">
            <x-ui.field-value label="Loại" :value="$article->typeLabel()" />
            <x-ui.field-value label="Nhóm ngành" :value="$article->category ?? '—'" />
            <x-ui.field-value label="Trạng thái" :value="$article->status === 'published' ? 'Đã xuất bản' : 'Nháp'" />
            @if ($article->project)
                <x-ui.field-value label="Dự án" :value="$article->project->name" />
            @endif
        </div>

        @if ($article->tags)
            <div class="mb-4">
                @foreach ($article->tags as $tag)
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 mr-1">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        @if ($article->type === 'checklist')
            <ul class="space-y-2">
                @foreach ($article->checklist_items ?? [] as $item)
                    <li class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        <span>{{ $item['text'] ?? '' }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="prose max-w-none">{!! nl2br(e($article->body)) !!}</div>
        @endif
    </x-ui.card>

    <x-ui.card class="mt-4" title="Hành động">
        <div class="flex flex-wrap gap-2">
            @if ($article->status === 'draft')
                <form method="POST" action="{{ route('operator.knowledge.publish', $article->id) }}">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary">Xuất bản</button>
                </form>
                <form method="POST" action="{{ route('operator.knowledge.destroy', $article->id) }}" onsubmit="return confirm('Xóa bài viết này?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="operator-button">Xóa</button>
                </form>
            @else
                <form method="POST" action="{{ route('operator.knowledge.unpublish', $article->id) }}" onsubmit="return confirm('Gỡ xuất bản và chuyển về bản nháp?')">
                    @csrf
                    <button type="submit" class="operator-button operator-button-secondary">Gỡ xuất bản</button>
                </form>
            @endif
        </div>
    </x-ui.card>
@endsection
