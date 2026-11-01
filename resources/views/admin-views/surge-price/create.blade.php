@extends('layouts.admin.app')

@section('title', translate('Create new surge price'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/surge-price.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/surge-price.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
    <script type="text/javascript" src="{{ asset('public/assets/admin/js/moment.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('public/assets/admin/js/daterangepicker.min.js') }}"></script>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-trending-up"></i>
                <span>
                    {{ translate('Create new surge price') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Set the zone, modules and hours a delivery charge is raised for.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.surge-price.store') }}" method="post" id="surge_form">
            @csrf
            @include('admin-views.surge-price.partials._form')
        </form>
    </div>

    @include('admin-views.surge-price.partials._duration-modals')
@endsection

@push('script_2')
    @include('admin-views.surge-price.partials._duration-scripts')
@endpush
