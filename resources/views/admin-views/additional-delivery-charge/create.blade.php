@extends('layouts.admin.app')

@section('title', translate('New additional delivery charge'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/additional-delivery-charge.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/additional-delivery-charge.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-money"></i>
                <span>
                    {{ translate('messages.Additional_Delivery_Charge') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Pick the zone and modules this covers, then set what express costs and what a delay saves.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.additional-delivery-charge.store') }}" method="post" id="additional-charge-form">
            @csrf
            @include('admin-views.additional-delivery-charge.partials._form')

            @include('admin-views.partials._floating-submit-button', ['submitButtonText' => translate('Save information')])
        </form>
    </div>
@endsection

@include('admin-views.additional-delivery-charge.partials._form-scripts')
