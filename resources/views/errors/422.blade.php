@extends('errors.generic')

@section('title', '422 - Validation Error')

@php
    $error_title = 'Validation Failed';
    $error_message = 'The data you submitted is invalid. Please check your input and try again.';
@endphp
