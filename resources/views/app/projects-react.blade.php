@extends('layouts.app-layout')

@section('title', 'Projects - ' . config('app.name'))

@section('content')
    <x-shared.projects-wrapper 
        :user="Auth::user()" 
        :tenant="Auth::user()?->tenant"
    />
@endsection
