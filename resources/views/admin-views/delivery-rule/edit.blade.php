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
                    <p class="page-header-desc">{{ translate('Change what this zone charges to deliver, and the modules the rule prices.') }}</p>
                </div>
            </div>
        </div>

        <form action="{{ route("admin.business-settings.zone.delivery-rule.update", $rule->id) }}" method="post" id="delivery-rule-form" data-rule-id="{{ $rule->id }}">
            @csrf
            @include('admin-views.delivery-rule.partials._form', ['rule' => $rule])
        </form>
    </div>

    @include('admin-views.delivery-rule.partials._success-modal', ['isUpdate' => true])
@endsection

@push('script_2')
    {{-- Ahead of the form's own scripts on purpose: the confirm binds on the form
         element and holds its validating and ajax handlers back until the admin has
         answered, which only works if it is bound first. --}}
    @include('admin-views.partials._module-removal-warning', [
        'formId' => 'delivery-rule-form',
        'soloModules' => $soloModules ?? [],
        'warningTitle' => translate('These modules will be unavailable'),
        'warningBody' => translate('messages.:modules will be unavailable in :zone — this is the only delivery charge setup covering them, and a module with no delivery charge setup cannot be served.', ['zone' => $zoneName ?? '']),
    ])
    @include('admin-views.delivery-rule.partials._form-scripts')
@endpush
