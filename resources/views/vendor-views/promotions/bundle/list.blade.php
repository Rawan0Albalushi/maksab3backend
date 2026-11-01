@extends('layouts.vendor.app')

@section('title', translate('Bundle package'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.bundle._row_styles')
@endpush

@section('content')
    @include('partials.bundle._list_page')
@endsection

@push('script_2')
    @include('partials.bundle._confirm_scripts')
    @include('partials.bundle._list_scripts')
@endpush
