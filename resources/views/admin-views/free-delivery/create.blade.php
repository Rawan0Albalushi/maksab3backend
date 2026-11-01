@extends('layouts.admin.app')

@section('title', translate('Add new free delivery'))

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
            <h1 class="page-header-title mb-0">
                <i class="tio-gift"></i>
                <span>
                    {{ translate('Add new free delivery') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Pick the zone and modules this offer covers, and the condition that makes delivery free.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.free-delivery.store') }}" method="post"
            id="free-delivery-form">
            @csrf
            @include('admin-views.free-delivery.partials._form', ['setup' => null])
        </form>
    </div>
@endsection

@push('script_2')
    @include('admin-views.free-delivery.partials._form-scripts')
@endpush
