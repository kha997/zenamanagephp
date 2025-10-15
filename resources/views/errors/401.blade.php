@extends('errors.generic')

@section('title', '401 - Unauthorized')

@php
    $error_title = 'Authentication Required';
    $error_message = 'You need to log in to access this resource.';
@endphp
