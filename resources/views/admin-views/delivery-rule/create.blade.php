@extends('layouts.admin.app')

@section('title', translate('Delivery rule setup'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/surge-price.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/surge-price.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/condition.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{ translate('Delivery rule setup') }}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Set what a zone charges to deliver, and pick the modules the rule prices.') }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.business-settings.zone.delivery-rule.store') }}" method="post" id="delivery-rule-form">
            @csrf
            @include('admin-views.delivery-rule.partials._form', ['rule' => null])
        </form>
    </div>

    @include('admin-views.delivery-rule.partials._success-modal')
@endsection

@push('script_2')
    @include('admin-views.delivery-rule.partials._form-scripts')
@endpush
