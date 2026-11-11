@extends('layouts.operator')

@section('title', 'Tạo mẫu công việc')
@section('page_title', 'Tạo mẫu công việc')

@section('content')
    {{-- GAP-123: create an empty draft template, then add steps on the edit page. --}}
    <x-ui.page-header title="Tạo mẫu công việc" description="Đặt mã và tên, sau đó thêm các bước.">
        <x-ui.button-link :href="route('app.work-templates.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    <x-ui.card>
        <form method="POST" action="{{ route('app.work-templates.store') }}" class="grid max-w-xl gap-3 p-4" data-testid="work-template-create-form">
            @csrf
            <label class="grid gap-1 text-sm">Mã mẫu
                <input type="text" name="code" maxlength="100" required class="operator-input" value="{{ old('code') }}" placeholder="VD: WT-NHA-PHO">
            </label>
            <label class="grid gap-1 text-sm">Tên mẫu
                <input type="text" name="name" maxlength="255" required class="operator-input" value="{{ old('name') }}" placeholder="VD: Nhà phố 3 tầng">
            </label>
            <label class="grid gap-1 text-sm">Mô tả
                <textarea name="description" rows="2" maxlength="2000" class="operator-input">{{ old('description') }}</textarea>
            </label>
            <div><button type="submit" class="operator-button operator-button-primary">Tạo và thêm bước</button></div>
        </form>
    </x-ui.card>
@endsection
