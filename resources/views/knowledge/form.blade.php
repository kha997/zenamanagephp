@extends('layouts.operator')

@section('title', $article ? 'Sửa bài viết' : 'Viết bài mới')
@section('page_title', $article ? 'Sửa bài viết' : 'Viết bài mới')

@section('content')
    <x-ui.page-header
        :title="$article ? 'Sửa bài viết' : 'Viết bài mới'"
        description="SOP, checklist hoặc bài học công trình."
    />

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ $article ? route('operator.knowledge.update', $article->id) : route('operator.knowledge.store') }}">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="type">Loại</label>
                    @if ($article)
                        <input type="text" value="{{ $article->typeLabel() }}" class="operator-input" disabled>
                        <input type="hidden" name="type" value="{{ $article->type }}">
                    @else
                        <select id="type" name="type" class="operator-input" required>
                            @foreach ($types as $type)
                                <option value="{{ $type }}">
                                    {{ ['sop' => 'Quy trình chuẩn (SOP)', 'checklist' => 'Checklist', 'lesson_learned' => 'Bài học công trình'][$type] ?? $type }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="operator-field">
                    <label for="title">Tiêu đề</label>
                    <input type="text" id="title" name="title" value="{{ old('title', $article->title ?? '') }}" class="operator-input" required maxlength="255">
                </div>
                <div class="operator-field">
                    <label for="category">Nhóm ngành</label>
                    <input type="text" id="category" name="category" value="{{ old('category', $article->category ?? '') }}" class="operator-input" placeholder="VD: hoàn thiện, PCCC...">
                </div>
                <div class="operator-field">
                    <label for="tags">Tags (phân tách bằng dấu phẩy)</label>
                    <input type="text" id="tags_input" class="operator-input" placeholder="VD: chong-tham, san-thuong" value="{{ old('tags') ? implode(',', old('tags')) : implode(',', $article->tags ?? []) }}">
                    <div id="tags-hidden"></div>
                </div>
            </div>

            <div class="operator-field mt-3" id="body-field">
                <label for="body">Nội dung</label>
                <textarea id="body" name="body" class="operator-input" rows="10">{{ old('body', $article->body ?? '') }}</textarea>
            </div>

            <div class="operator-field mt-3" id="checklist-field">
                <label>Danh mục kiểm tra</label>
                <div id="checklist-items">
                    @php $items = old('checklist_items', $article->checklist_items ?? []); @endphp
                    @forelse ($items as $item)
                        <div class="flex gap-2 mb-2 checklist-row">
                            <input type="text" name="checklist_items[][text]" value="{{ $item['text'] ?? $item }}" class="operator-input flex-1">
                        </div>
                    @empty
                        <div class="flex gap-2 mb-2 checklist-row">
                            <input type="text" name="checklist_items[][text]" value="" class="operator-input flex-1">
                        </div>
                    @endforelse
                </div>
                <button type="button" class="operator-button operator-button-secondary text-sm" onclick="addChecklistRow()">+ Thêm mục</button>
            </div>

            <div class="operator-field mt-3" id="project-field">
                <label for="project_id">Dự án liên quan (tùy chọn)</label>
                <input type="text" id="project_id" name="project_id" value="{{ old('project_id', $article->project_id ?? '') }}" class="operator-input" placeholder="ID dự án">
            </div>

            <div class="mt-4">
                <button type="submit" class="operator-button operator-button-primary">{{ $article ? 'Lưu' : 'Tạo bản nháp' }}</button>
            </div>
        </form>
    </x-ui.card>

    <script>
        function addChecklistRow() {
            const container = document.getElementById('checklist-items');
            const row = document.createElement('div');
            row.className = 'flex gap-2 mb-2 checklist-row';
            row.innerHTML = '<input type="text" name="checklist_items[][text]" value="" class="operator-input flex-1">';
            container.appendChild(row);
        }

        function toggleFieldsByType() {
            const typeInput = document.getElementById('type');
            const type = typeInput ? typeInput.value : '{{ $article->type ?? "sop" }}';
            document.getElementById('checklist-field').style.display = (type === 'checklist') ? '' : 'none';
            document.getElementById('project-field').style.display = (type === 'lesson_learned') ? '' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleFieldsByType();
            const typeInput = document.getElementById('type');
            if (typeInput && typeInput.tagName === 'SELECT') {
                typeInput.addEventListener('change', toggleFieldsByType);
            }

            const tagsInput = document.getElementById('tags_input');
            const form = tagsInput ? tagsInput.closest('form') : null;
            if (form) {
                form.addEventListener('submit', function () {
                    document.getElementById('tags-hidden').innerHTML = '';
                    const tags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t.length > 0);
                    tags.forEach(function (tag) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'tags[]';
                        input.value = tag;
                        document.getElementById('tags-hidden').appendChild(input);
                    });
                });
            }
        });
    </script>
@endsection
