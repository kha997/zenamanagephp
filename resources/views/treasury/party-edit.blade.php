@extends('layouts.operator')

@section('title', 'Sửa đối tác tài chính')
@section('page_title', 'Sửa đối tác tài chính')

@section('content')
    <x-ui.page-header title="Sửa đối tác tài chính" description="{{ $party->name }}">
        <x-ui.button-link :href="route('operator.treasury.parties.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        @include('treasury._errors')
        <form method="POST" action="{{ route('operator.treasury.parties.update', $party->id) }}" class="space-y-5">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="party_type">Loại <span class="text-rose-600">*</span></label>
                    <select id="party_type" name="party_type" class="operator-input" required>
                        @foreach ($partyTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('party_type', $party->party_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="name">Tên <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name', $party->name) }}" maxlength="255" required>
                </div>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Lưu</button>
        </form>
    </x-ui.card>
@endsection
