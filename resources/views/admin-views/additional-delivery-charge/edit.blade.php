@extends('layouts.admin.app')

@section('title', translate('Update additional delivery charge'))

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
            <p class="page-header-desc">{{ translate('Change the zone and modules this covers, or what express costs and what a delay saves.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.additional-delivery-charge.update', [$setup->id]) }}" method="post" id="additional-charge-form">
            @csrf
            @include('admin-views.additional-delivery-charge.partials._form')

            @include('admin-views.partials._floating-submit-button', ['submitButtonText' => translate('Save information')])
        </form>
    </div>
@endsection

{{-- Wrapped in the stack this view's scripts already use: everything outside a section in a
     child view is discarded, so the dialog's own markup has to be pushed to reach the page.
     Ahead of the form's own scripts on purpose — the confirm binds on the form element and holds
     its validating handlers back until the admin has answered, which only works if it is first. --}}
@push('script_2')
    @include('admin-views.partials._module-removal-warning', [
        'formId' => 'additional-charge-form',
        'soloModules' => $soloModules ?? [],
        'warningTitle' => translate('Additional delivery charge will stop applying'),
        'warningBody' => translate('messages.Additional Delivery Charge will no longer apply to :modules in :zone. They stay available in the zone and are charged the normal delivery fee.', ['zone' => $zoneName ?? '']),
    ])
@endpush
@include('admin-views.additional-delivery-charge.partials._form-scripts')
