@extends('layouts.admin.app')

@section('title',\App\CentralLogics\Helpers::get_business_settings('business_name', false)??translate('Dashboard'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')

@endsection

@push('script')

@endpush
