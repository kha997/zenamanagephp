@extends('layouts.operator')

@section('title', 'Sửa ví')
@section('page_title', 'Sửa ví')

@section('content')
    <x-ui.page-header title="Sửa ví" description="{{ $project->name }} — {{ $wallet->name }}">
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        @include('treasury._errors')
        <form method="POST" action="{{ route('operator.treasury.projects.wallets.update', [$project->id, $wallet->id]) }}" class="space-y-5">
            @csrf
            @include('treasury._wallet-fields', ['wallet' => $wallet])
            <button type="submit" class="operator-button">Lưu</button>
        </form>
    </x-ui.card>
@endsection
