@extends('layouts.admin.app')

@section('title', translate('Update bundle'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.promotion._item_picker_styles')
    @include('partials.bundle._row_styles')
@endpush

@section('content')
    @include('partials.bundle._form_page')
@endsection

@push('script_2')
    @include('partials.bundle._form_scripts')
@endpush
