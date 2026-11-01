@extends('layouts.admin.app')

@section('title',translate('Update banner'))

@php($isServiceModule = \Illuminate\Support\Facades\Config::get('module.current_module_type') == 'service' && service_addon_active())

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/third-party-setup.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/view-pages/banner-form.css')}}">
@endpush

@section('content')
    <div class="content container-fluid tps bnr">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
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
            <div class="page-header-actions">
                <a href="{{ route('admin.banner.add-new') }}" class="btn btn--reset">
                    <i class="tio-arrow-backward"></i> {{ translate('messages.Back') }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <form id="banner_form">
                    @include('admin-views.banner.partials._form', [
                        'banner' => $banner,
                        'isServiceModule' => $isServiceModule,
                        'submitLabel' => translate('Update'),
                        'submitIcon' => 'tio-save',
                    ])
                </form>
            </div>

            <div class="col-xl-4">
                @include('admin-views.banner.partials._preview', ['banner' => $banner])
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    @php($bannerFormConfig = [
        'moduleId' => (int) $banner->module_id,
        'isEdit' => true,
        'selectedItemId' => $banner->type === 'item_wise' ? $banner->data : null,
        'itemSourceUrl' => $isServiceModule ? route('admin.service.get-services') : url('/') . '/admin/item/get-items',
        'storeSourceUrl' => route('admin.store.get-stores'),
        'submitUrl' => route('admin.banner.update', [$banner->id]),
        'redirectUrl' => url()->full(),
        'successMessage' => translate('Updated successfully'),
        'ownerPlaceholder' => $isServiceModule ? translate('Select provider') : translate('Select store'),
        'lang' => [
            'notSet' => translate('messages.Not set'),
            'selectStore' => $isServiceModule ? translate('Please select a provider') : translate('Please select a store'),
            'selectItem' => $isServiceModule ? translate('Please select a service') : translate('Please select an item'),
        ],
    ])
    <script>
        "use strict";

        window.bannerFormConfig = @json($bannerFormConfig);
    </script>
    <script src="{{asset('public/assets/admin')}}/js/view-pages/banner-form.js"></script>
@endpush
