@extends('layouts.admin.app')

@section('title', translate('messages.Flutter web landing page'))

@section('content')

    <div class="content container-fluid">
        <div class="page-header pb-0">
            <div class="d-flex flex-wrap justify-content-between">
                <div>
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/flutter.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{ translate('messages.Flutter web landing page') }}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('The join-us block inviting stores and deliverymen to sign up.') }}</p>
                </div>
                <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center" type="button" data-toggle="modal"
                    data-target="#how-it-works">
                    <strong class="mr-2">{{ translate('See how it works') }}</strong>
                    <div>
                        <i class="tio-info-outined"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4 mt-2">
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                @include('admin-views.business-settings.landing-page-settings.top-menu-links.flutter-landing-page-links')
            </div>
        </div>
        @php($join_seller_flutter_status = ($flutterSettings['join_seller_flutter_status'] ?? null)?->value)
        @php($join_DM_flutter_status = ($flutterSettings['join_DM_flutter_status'] ?? null)?->value)


        @php($join_seller_title = ($flutterSettings['join_seller_title'] ?? null))
        @php($join_seller_sub_title = ($flutterSettings['join_seller_sub_title'] ?? null))
        @php($join_seller_button_name = ($flutterSettings['join_seller_button_name'] ?? null))
        @php($join_delivery_man_title = ($flutterSettings['join_delivery_man_title'] ?? null))
        @php($join_delivery_man_sub_title = ($flutterSettings['join_delivery_man_sub_title'] ?? null))
        @php($join_delivery_man_button_name = ($flutterSettings['join_delivery_man_button_name'] ?? null))

        @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
        @if ($language)
            <ul class="nav nav-tabs mb-4 border-0">
                <li class="nav-item">
                    <a class="nav-link lang_link active" href="#"
                        id="default-link">{{ translate('Default') }}</a>
                </li>
                @foreach (json_decode($language) as $lang)
                    <li class="nav-item">
                        <a class="nav-link lang_link" href="#"
                            id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="tab-content">
            <div class="tab-pane fade show active">
                <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'join-seller') }}" method="post" id="join_seller_flutter_status_form">
                    @csrf
                    <input type="hidden" name="join_seller_flutter_status" value="{{ $join_seller_flutter_status ?? 0 }}">
                </form>

                <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'join-seller') }}"
                    method="POST">
                    @csrf

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-3 mt-3">
                                <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span>
                                <span>{{ translate('Join as a Seller Section') }}</span>
                            </h5>

                            <label class="toggle-switch justify-content-end  rounded">
                                <input type="checkbox" data-id="join_seller_flutter_status" data-type="status"
                                    data-image-on="{{ asset('/public/assets/admin/img/modal/seller-app-on.png') }}"
                                    data-image-off="{{ asset('/public/assets/admin/img/modal/seller-app-off.png') }}"
                                    data-title-on="<strong>{{ translate('messages.Want to enable Join as a Seller Section?') }}</strong>"
                                    data-title-off="<strong>{{ translate('messages.Want to disable Join as a Seller Section?') }}</strong>"
                                    data-text-on="<p>{{ translate('messages.If you enable this, Join as a Seller Section will be visible.') }}</p>"
                                    data-text-off="<p>{{ translate('If you disable this, join as a seller section will not be visible.') }}</p>"
                                    class="status toggle-switch-input dynamic-checkbox" value="1"
                                    name="" id="join_seller_flutter_status"
                                    {{ $join_seller_flutter_status == 1 ? 'checked' : '' }}>
                                <span class="toggle-switch-label text">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                        <div class="card-body {{ $join_seller_flutter_status != 1 ? 'd-none' : '' }}">
                            @if ($language)
                                <div class="row g-3 lang_form default-form">
                                    <div class="col-sm-6">
                                        <label for="join_seller_title" class="form-label">{{ translate('Title') }}
                                            ({{ translate('Default') }})<span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input type="text" id="join_seller_title" maxlength="20"
                                            name="join_seller_title[]" class="form-control"
                                            value="{{ $join_seller_title?->getRawOriginal('value') ?? '' }}"
                                            placeholder="{{ translate('Enter title') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_seller_button_name"
                                            class="form-label">{{ translate('Button name') }}
                                            ({{ translate('Default') }})<span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 15">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="join_seller_button_name" type="text" maxlength="15"
                                            name="join_seller_button_name[]" class="form-control"
                                            value="{{ $join_seller_button_name?->getRawOriginal('value') ?? '' }}"
                                            placeholder="{{ translate('Enter button name') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_seller_sub_title" class="form-label">{{ translate('Sub Title') }}
                                            ({{ translate('Default') }})<span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 60">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <textarea id="join_seller_sub_title" placeholder="{{ translate('Enter subtitle') }}" maxlength="60"
                                            name="join_seller_sub_title[]" class="form-control" rows="2">{{ $join_seller_sub_title?->getRawOriginal('value') ?? '' }}</textarea>
                                    </div>

                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                @foreach (json_decode($language) as $lang)
                                    <?php
                                    if (isset($join_seller_title->translations) && count($join_seller_title->translations)) {
                                        $join_seller_title_translate = [];
                                        foreach ($join_seller_title->translations as $t) {
                                            if ($t->locale == $lang && $t->key == 'join_seller_title') {
                                                $join_seller_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }
                                    }
                                    if (isset($join_seller_sub_title->translations) && count($join_seller_sub_title->translations)) {
                                        $join_seller_sub_title_translate = [];
                                        foreach ($join_seller_sub_title->translations as $t) {
                                            if ($t->locale == $lang && $t->key == 'join_seller_sub_title') {
                                                $join_seller_sub_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }
                                    }
                                    if (isset($join_seller_button_name->translations) && count($join_seller_button_name->translations)) {
                                        $join_seller_button_name_translate = [];
                                        foreach ($join_seller_button_name->translations as $t) {
                                            if ($t->locale == $lang && $t->key == 'join_seller_button_name') {
                                                $join_seller_button_name_translate[$lang]['value'] = $t->value;
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="row g-3 d-none lang_form" id="{{ $lang }}-form">
                                        <div class="col-sm-6">
                                            <label for="join_seller_title{{ $lang }}"
                                                class="form-label">{{ translate('Title') }}
                                                ({{ strtoupper($lang) }})<span class="form-label-secondary"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Character limit') }}: 20">
                                                    <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="">
                                                </span></label>
                                            <input type="text" id="join_seller_title{{ $lang }}"
                                                maxlength="20" name="join_seller_title[]" class="form-control"
                                                value="{{ $join_seller_title_translate[$lang]['value'] ?? '' }}"
                                                placeholder="{{ translate('Enter title') }}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="join_seller_button_name{{ $lang }}"
                                                class="form-label">{{ translate('Button name') }}
                                                ({{ strtoupper($lang) }})
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('Character limit') }}: 15">
                                                    <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="">
                                                </span></label>
                                            <input id="join_seller_button_name{{ $lang }}" type="text"
                                                maxlength="15" name="join_seller_button_name[]" class="form-control"
                                                value="{{ $join_seller_button_name_translate[$lang]['value'] ?? '' }}"
                                                placeholder="{{ translate('Enter button name') }}">
                                        </div>

                                        <div class="col-sm-6">
                                            <label for="join_seller_sub_title{{ $lang }}"
                                                class="form-label">{{ translate('Sub Title') }}
                                                ({{ strtoupper($lang) }})<span class="form-label-secondary"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Character limit') }}: 60">
                                                    <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="">
                                                </span></label>
                                            <textarea id="join_seller_sub_title{{ $lang }}" type="text"
                                                placeholder="{{ translate('Enter subtitle') }}" maxlength="60" name="join_seller_sub_title[]"
                                                class="form-control" rows="2">{{ $join_seller_sub_title_translate[$lang]['value'] ?? '' }}</textarea>
                                        </div>

                                    </div>
                                    <input type="hidden" name="lang[]" value="{{ $lang }}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="join_seller_title" class="form-label">{{ translate('Title') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input type="text" id="join_seller_title" maxlength="20"
                                            name="join_seller_title[]" class="form-control"
                                            placeholder="{{ translate('Enter title') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_seller_button_name"
                                            class="form-label">{{ translate('Button name') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 15">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="join_seller_button_name" type="text" maxlength="15"
                                            name="join_seller_button_name[]" class="form-control"
                                            placeholder="{{ translate('Enter button name') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_seller_sub_title"
                                            class="form-label">{{ translate('Sub Title') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 60">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <textarea id="join_seller_sub_title" value="join_seller_sub_title" maxlength="60" name="join_seller_sub_title[]"
                                            class="form-control" placeholder="{{ translate('Enter subtitle') }}" rows="2"></textarea>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                                <button type="submit" class="btn btn--primary mb-2"><i class="tio-save"></i> {{ translate('Save') }}</button>
                            </div>
                        </div>
                    </div>
                </form>


                <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'join-delivery') }}" method="post" id="join_DM_flutter_status_form">
                    @csrf
                    <input type="hidden" name="join_DM_flutter_status" value="{{ $join_DM_flutter_status ?? 0 }}">
                </form>

                <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'join-delivery') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mt-4 card">
                        <div class="card-header">
                            <h5 class="card-title mb-3 mt-3">
                                <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span>
                                <span>{{ translate('Join as a Deliveryman Section') }}</span>
                            </h5>

                            <label class="toggle-switch justify-content-end  rounded">
                                <input type="checkbox" data-id="join_DM_flutter_status" data-type="status"
                                    data-image-on="{{ asset('/public/assets/admin/img/modal/home-delivery-on.png') }}"
                                    data-image-off="{{ asset('/public/assets/admin/img/modal/home-delivery-off.png') }}"
                                    data-title-on="<strong>{{ translate('messages.Want to enable Join as a Deliveryman Section?') }}</strong>"
                                    data-title-off="<strong>{{ translate('messages.Want to disable Join as a Deliveryman Section?') }}</strong>"
                                    data-text-on="<p>{{ translate('messages.If you enable this, Join as a Deliveryman Section will be visible.') }}</p>"
                                    data-text-off="<p>{{ translate('If you disable this, join as a deliveryman section will not be visible.') }}</p>"
                                    class="status toggle-switch-input dynamic-checkbox" value="1"
                                    name="" id="join_DM_flutter_status"
                                    {{ $join_DM_flutter_status == 1 ? 'checked' : '' }}>
                                <span class="toggle-switch-label text">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                        <div class="card-body {{ $join_DM_flutter_status != 1 ? 'd-none' : '' }}">

                            @if ($language)
                                <div class="row g-3 lang_form default-form">
                                    <div class="col-sm-6">
                                        <label for="join_delivery_man_title" class="form-label">{{ translate('Title') }}
                                            ({{ translate('Default') }})<span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input type="text" id="join_delivery_man_title" maxlength="20"
                                            name="join_delivery_man_title[]" class="form-control"
                                            value="{{ $join_delivery_man_title?->getRawOriginal('value') ?? '' }}"
                                            placeholder="{{ translate('Enter title') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_delivery_man_button_name"
                                            class="form-label">{{ translate('Button name') }}
                                            ({{ translate('Default') }})<span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 15">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="join_delivery_man_button_name" type="text" maxlength="15"
                                            name="join_delivery_man_button_name[]" class="form-control"
                                            value="{{ $join_delivery_man_button_name?->getRawOriginal('value') ?? '' }}"
                                            placeholder="{{ translate('Enter button name') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_delivery_man_sub_title"
                                            class="form-label">{{ translate('Sub Title') }}
                                            ({{ translate('Default') }})<span class="form-label-secondary"
                                                data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 60">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <textarea id="join_delivery_man_sub_title" placeholder="{{ translate('Enter subtitle') }}"
                                            maxlength="60" name="join_delivery_man_sub_title[]" class="form-control" rows="2">{{ $join_delivery_man_sub_title?->getRawOriginal('value') ?? '' }}</textarea>
                                    </div>


                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                @foreach (json_decode($language) as $lang)
                                    <?php
                                    if (isset($join_delivery_man_title->translations) && count($join_delivery_man_title->translations)) {
                                        $join_delivery_man_title_translate = [];
                                        foreach ($join_delivery_man_title->translations as $t) {
                                            if ($t->locale == $lang && $t->key == 'join_delivery_man_title') {
                                                $join_delivery_man_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }
                                    }
                                    if (isset($join_delivery_man_sub_title->translations) && count($join_delivery_man_sub_title->translations)) {
                                        $join_delivery_man_sub_title_translate = [];
                                        foreach ($join_delivery_man_sub_title->translations as $t) {
                                            if ($t->locale == $lang && $t->key == 'join_delivery_man_sub_title') {
                                                $join_delivery_man_sub_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }
                                    }
                                    if (isset($join_delivery_man_button_name->translations) && count($join_delivery_man_button_name->translations)) {
                                        $join_delivery_man_button_name_translate = [];
                                        foreach ($join_delivery_man_button_name->translations as $t) {
                                            if ($t->locale == $lang && $t->key == 'join_delivery_man_button_name') {
                                                $join_delivery_man_button_name_translate[$lang]['value'] = $t->value;
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="row g-3 d-none lang_form" id="{{ $lang }}-form1">
                                        <div class="col-sm-6">
                                            <label for="join_delivery_man_title{{ $lang }}"
                                                class="form-label">{{ translate('Title') }}
                                                ({{ strtoupper($lang) }})
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('Character limit') }}: 20">
                                                    <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="">
                                                </span></label>
                                            <input type="text" id="join_delivery_man_title{{ $lang }}"
                                                maxlength="20" name="join_delivery_man_title[]" class="form-control"
                                                value="{{ $join_delivery_man_title_translate[$lang]['value'] ?? '' }}"
                                                placeholder="{{ translate('Enter title') }}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="join_delivery_man_button_name{{ $lang }}"
                                                class="form-label">{{ translate('Button name') }}
                                                ({{ strtoupper($lang) }})<span class="form-label-secondary"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Character limit') }}: 15">
                                                    <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="">
                                                </span></label>
                                            <input type="text" id="join_delivery_man_button_name{{ $lang }}"
                                                maxlength="15" name="join_delivery_man_button_name[]"
                                                class="form-control"
                                                value="{{ $join_delivery_man_button_name_translate[$lang]['value'] ?? '' }}"
                                                placeholder="{{ translate('Enter button name') }}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="join_delivery_man_sub_title{{ $lang }}"
                                                class="form-label">{{ translate('Sub Title') }}
                                                ({{ strtoupper($lang) }})<span class="form-label-secondary"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Character limit') }}: 60">
                                                    <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                        alt="">
                                                </span></label>
                                            <textarea id="join_delivery_man_sub_title{{ $lang }}"
                                                placeholder="{{ translate('Enter subtitle') }}" maxlength="60" name="join_delivery_man_sub_title[]"
                                                class="form-control" rows="2">{{ $join_delivery_man_sub_title_translate[$lang]['value'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{ $lang }}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="join_delivery_man_title"
                                            class="form-label">{{ translate('Title') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="join_delivery_man_title" type="text" maxlength="20"
                                            name="join_delivery_man_title[]" class="form-control"
                                            placeholder="{{ translate('Enter title') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_delivery_man_sub_title"
                                            class="form-label">{{ translate('Sub Title') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 60">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="join_delivery_man_sub_title" type="text" maxlength="60"
                                            name="join_delivery_man_sub_title[]" class="form-control"
                                            placeholder="{{ translate('Enter subtitle') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="join_delivery_man_button_name"
                                            class="form-label">{{ translate('Button name') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 15">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="join_delivery_man_button_name" type="text" maxlength="15"
                                            name="join_delivery_man_button_name[]" class="form-control"
                                            placeholder="{{ translate('Enter button name') }}">
                                    </div>

                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                                <button type="submit" class="btn btn--primary mb-2"><i class="tio-save"></i> {{ translate('Save') }}</button>
                            </div>
                        </div>
                    </div>
                </form>


            </div>
        </div>

        @include('admin-views.business-settings.landing-page-settings.partial.how-it-work-flutter')
    </div>

@endsection
