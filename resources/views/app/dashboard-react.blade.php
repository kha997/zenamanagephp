@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <x-shared.dashboard-wrapper 
        :user="Auth::user()" 
        :tenant="Auth::user()?->tenant"
    />
@endsection
