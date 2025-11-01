@extends('errors.generic')

@section('title', '400 - Bad Request')

@php
    $error_title = 'Bad Request';
    $error_message = 'The request was invalid or malformed. Please check your input and try again.';
@endphp
