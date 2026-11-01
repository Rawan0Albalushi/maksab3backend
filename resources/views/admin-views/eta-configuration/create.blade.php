@extends('layouts.admin.app')

@section('title', translate('Create ETA configuration'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/surge-price.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/surge-price.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/eta-configuration.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/eta-configuration.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-time"></i>
                <span>
                    {{ translate('Create ETA configuration') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Set how a zone estimates delivery time, and which modules the estimate covers.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.eta-configuration.store') }}" method="post"
            id="eta-configuration-form">
            @csrf
            @include('admin-views.eta-configuration.partials._form')
        </form>
    </div>
@endsection

@push('script_2')
    @include('admin-views.eta-configuration.partials._form-scripts')
@endpush
