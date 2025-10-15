@extends('errors.generic')

@section('title', '500 - Internal Server Error')

@php
    $error_title = 'Internal Server Error';
    $error_message = 'An unexpected error occurred on our end. We have been notified and are working to fix it.';
@endphp
