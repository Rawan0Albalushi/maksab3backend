@extends('layouts.admin.app')

@section('title', translate('Update ETA configuration'))

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
                    {{ translate('Update ETA configuration') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change how this zone estimates delivery time, and which modules the estimate covers.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.eta-configuration.update', [$configuration->id]) }}"
            method="post" id="eta-configuration-form">
            @csrf
            @include('admin-views.eta-configuration.partials._form')
        </form>
    </div>
@endsection

@push('script_2')
    {{-- Ahead of the form's own scripts on purpose: the confirm binds on the form
         element and holds its validating and ajax handlers back until the admin has
         answered, which only works if it is bound first. --}}
    @include('admin-views.partials._module-removal-warning', [
        'formId' => 'eta-configuration-form',
        'soloModules' => $soloModules ?? [],
        'warningTitle' => translate('These modules will be unavailable'),
        'warningBody' => translate('messages.:modules will be unavailable in :zone — this is the only ETA configuration covering them, and a module with no ETA configuration cannot be served.', ['zone' => $zoneName ?? '']),
    ])
    @include('admin-views.eta-configuration.partials._form-scripts')
@endpush
