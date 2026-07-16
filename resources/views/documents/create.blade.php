@extends('layouts.operator')

@section('title', 'Tải tài liệu lên')
@section('page_title', 'Tải tài liệu lên')

@section('content')
    <x-ui.page-header
        title="Tải tài liệu lên"
        description="Tải tài liệu cho dự án — tối đa 10MB mỗi file."
    >
        <x-ui.button-link href="/app/documents" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin tài liệu">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('app.documents.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                    <input id="title" name="title" type="text" class="operator-input" value="{{ old('title') }}" required>
                </div>

                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $projectId) === (string) $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="document_type">Loại tài liệu <span class="text-rose-600">*</span></label>
                    <select id="document_type" name="document_type" class="operator-select" required>
                        @foreach (['drawing' => 'Bản vẽ', 'specification' => 'Chỉ dẫn kỹ thuật', 'report' => 'Báo cáo', 'contract' => 'Hợp đồng', 'other' => 'Khác'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="file">File <span class="text-rose-600">*</span></label>
                    <input id="file" name="file" type="file" class="operator-input" required>
                </div>
            </div>

            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea">{{ old('description') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tải lên</button>
        </form>
    </x-ui.card>
@endsection
