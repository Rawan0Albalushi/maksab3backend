@extends('layouts.admin.app')

@section('title', translate('Edit free delivery'))

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
                    {{ translate('Edit free delivery') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change the zone and modules this offer covers, or the condition that makes delivery free.') }}</p>
        </div>

        <form action="{{ route('admin.business-settings.zone.free-delivery.update', $setup->id) }}" method="post"
            id="free-delivery-form">
            @csrf
            @include('admin-views.free-delivery.partials._form', ['setup' => $setup])
        </form>
    </div>
@endsection

@push('script_2')
    {{-- Ahead of the form's own scripts on purpose: the confirm binds on the form
         element and holds its validating and ajax handlers back until the admin has
         answered, which only works if it is bound first. --}}
    @include('admin-views.partials._module-removal-warning', [
        'formId' => 'free-delivery-form',
        'soloModules' => $soloModules ?? [],
        'warningTitle' => translate('messages.Free Delivery Will Stop Applying'),
        'warningBody' => translate('messages.Free Delivery will no longer apply to :modules in :zone. They stay available in the zone and pay for delivery as normal.', ['zone' => $zoneName ?? '']),
    ])
    @include('admin-views.free-delivery.partials._form-scripts')
@endpush
