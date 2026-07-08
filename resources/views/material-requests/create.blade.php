@extends('layouts.operator')

@section('title', 'Tạo yêu cầu vật tư')
@section('page_title', 'Tạo yêu cầu vật tư')

@section('content')
    <x-ui.page-header
        title="Tạo yêu cầu vật tư"
        description="Nhập thông tin yêu cầu vật tư và lưu ở trạng thái draft."
    >
        <x-ui.button-link :href="route('operator.material-requests.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin yêu cầu">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.material-requests.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án</label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="required_date">Ngày cần</label>
                    <input id="required_date" name="required_date" type="date" class="operator-input" value="{{ old('required_date') }}">
                </div>

                <div class="operator-field">
                    <label for="estimated_cost">Chi phí dự kiến</label>
                    <input id="estimated_cost" name="estimated_cost" type="text" inputmode="decimal" data-money class="operator-input" value="{{ old('estimated_cost') }}" placeholder="vd: 1.500.000">
                </div>
            </div>

            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea" required>{{ old('description') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo yêu cầu</button>
        </form>
    </x-ui.card>
@endsection
