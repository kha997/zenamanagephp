@extends('layouts.operator')

@section('title', 'Tạo BOQ')
@section('page_title', 'Tạo BOQ')

@section('content')
    <x-ui.page-header
        title="Tạo BOQ"
        description="Tạo bảng khối lượng mới cho dự án."
    >
        <x-ui.button-link :href="route('operator.boqs.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin BOQ">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.boqs.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('project_id')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="code">Mã BOQ <span class="text-rose-600">*</span></label>
                    <input id="code" name="code" type="text" class="operator-input" value="{{ old('code', 'BOQ-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4))) }}" maxlength="100" required placeholder="VD: BOQ-001">
                    @error('code')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="operator-field">
                <label for="name">Tên <span class="text-rose-600">*</span></label>
                <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" maxlength="255" required>
                @error('name')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea">{{ old('description') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo BOQ</button>
        </form>
    </x-ui.card>
@endsection
