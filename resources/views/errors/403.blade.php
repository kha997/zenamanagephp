@extends('errors.generic')

@section('title', '403 - Forbidden')

@php
    $error_title = 'Access Denied';
    $error_message = 'You do not have permission to access this resource.';
@endphp
