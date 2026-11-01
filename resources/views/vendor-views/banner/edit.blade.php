@extends('layouts.vendor.app')

@section('title',translate('Update banner'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/banner-form.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps bnr">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Update banner')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change this banner\'s artwork, where it links to and whether customers can see it.') }}</p>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <form action="{{route('vendor.banner.update', [$banner->id])}}" method="POST"
                      enctype="multipart/form-data" class="custom-validation" id="banner_form">
                    @csrf
                    @include('vendor-views.banner.partials._form', [
                        'submitLabel' => translate('Update'),
                        'submitIcon' => 'tio-save',
                    ])
                </form>
            </div>

            <div class="col-xl-4">
                @include('vendor-views.banner.partials._preview')
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        window.vendorBannerFormConfig = {
            isEdit: true,
            notSet: @json(translate('messages.Not set'))
        };
    </script>
    <script src="{{asset('public/assets/admin')}}/js/view-pages/vendor-banner-form.js"></script>
@endpush
