@extends('layouts.admin.app')

@section('title', translate('Update surge price'))

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
                    {{ translate('Update surge price') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change the zone, modules and hours this delivery charge uplift applies to.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.surge-price.update', [$surge->id]) }}" method="post" id="surge_form">
            @csrf
            @include('admin-views.surge-price.partials._form')
        </form>
    </div>

    @include('admin-views.surge-price.partials._duration-modals')
@endsection

@push('script_2')
    {{-- Ahead of the form's own scripts on purpose: the confirm binds on the form
         element and holds its validating and ajax handlers back until the admin has
         answered, which only works if it is bound first. --}}
    @include('admin-views.partials._module-removal-warning', [
        'formId' => 'surge_form',
        'soloModules' => $soloModules ?? [],
        'warningTitle' => translate('Surge price will stop applying'),
        'warningBody' => translate('messages.Surge Price will no longer apply to :modules in :zone. They stay available in the zone and are charged the normal delivery fee.', ['zone' => $zoneName ?? '']),
    ])
    @include('admin-views.surge-price.partials._duration-scripts')
@endpush
