@extends('errors.generic')

@section('title', '429 - Too Many Requests')

@php
    $error_title = 'Rate Limited';
    $error_message = 'You have made too many requests. Please wait a moment before trying again.';
@endphp
