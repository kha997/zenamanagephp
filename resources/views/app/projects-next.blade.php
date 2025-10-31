@extends('layouts.app')

@section('title', 'Projects (New Design) - ' . config('app.name'))

@section('content')
    <x-shared.projects-next-wrapper 
        :user="Auth::user()" 
        :tenant="Auth::user()?->tenant"
    />
@endsection

