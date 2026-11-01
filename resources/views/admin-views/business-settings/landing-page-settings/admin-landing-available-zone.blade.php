@extends('layouts.admin.app')

@section('title',translate('Admin landing page'))

@section('content')
<div class="content container-fluid">
    <div class="page-header pb-0">
        <div class="d-flex flex-wrap justify-content-between">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/landing.svg')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('messages.Admin landing pages') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('The zones shown on the admin landing page as places you already deliver to.') }}</p>
            </div>
            <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center" type="button" data-toggle="modal" data-target="#how-it-works">
                <strong class="mr-2">{{translate('See how it works')}}</strong>
                <div>
                    <i class="tio-info-outined"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-20 mt-2">
        <div class="js-nav-scroller hs-nav-scroller-horizontal">
            @include('admin-views.business-settings.landing-page-settings.top-menu-links.admin-landing-page-links')
        </div>
    </div>
    @php($available_zone_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','available_zone_title')->first())
    @php($available_zone_short_description=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','available_zone_short_description')->first())
    @php($available_zone_image=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','available_zone_image')->first())
    @php($available_zone_status=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','available_zone_status')->first())
    @php($available_zone_status = $available_zone_status ? $available_zone_status->value : 0)
    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)

    <form id="zone-setup-form" action="{{ route('admin.business-settings.admin-landing-page-settings-update', 'available-zone-section') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        {{ translate('To view a list of all active zones on your') }} <a href="{{route('home')}}" target="_blank" class="text--underline text-006AE5">{{ translate('Admin landing') }}</a> {{ translate('Page') }} <br class="d-none d-md-inline-block"> {{ translate('Enable the')}} <strong>{{ translate('Available zones') }}</strong> {{translate('feature') }}
                    </div>
                    <div class="col-sm-6">
                        <label
                            class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                                <span class="line--limit-1 text--primary">
                                                    {{translate('messages.Available zone') }}
                                                </span>
                                            </span>
                            <input type="checkbox"
                                   data-id="available_zone_status"
                                   data-type="toggle"
                                   data-image-on="{{ asset('/public/assets/admin/img/modal/dm-tips-on.png') }}"
                                   data-image-off="{{ asset('/public/assets/admin/img/modal/dm-tips-off.png') }}"
                                   data-title-on="<strong>{{ translate('messages.Want to enable available zone?') }}</strong>"
                                   data-title-off="<strong>{{ translate('messages.Want to disable available zone?') }}</strong>"
                                   data-text-on="<p>{{ translate('messages.If you enable this, available zone section will be visible.') }}</p>"
                                   data-text-off="<p>{{ translate('messages.If you disable this, available zone section will not be visible.') }}</p>"
                                   class="status toggle-switch-input dynamic-checkbox-toggle"
                                   value="1"
                                   name="available_zone_status" id="available_zone_status"
                                {{ $available_zone_status == 1 ? 'checked' : '' }}>
                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card shadow--card-2">
                    <div class="card-body">
                        @if($language)
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active"
                                       href="#"
                                       id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach (json_decode($language) as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link"
                                           href="#"
                                           id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($language)
                            <div class="lang_form"
                                 id="default-form">
                                <div class="form-group">
                                    <label class="input-label"
                                           for="default_title">{{ translate('messages.Title') }}
                                        ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span>
                                        <span class="form-label-secondary text-danger"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                    </label>
                                    <input required type="text" name="available_zone_title[]" id="default_title" maxlength="50"
                                           class="form-control" placeholder="{{ translate('messages.Title') }}" value="{{$available_zone_title?->getRawOriginal('value')}}"
                                    >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                <div class="form-group mb-0">
                                    <label class="input-label"
                                           for="exampleFormControlInput1">{{ translate('Short description') }} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 200">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <textarea type="text" name="available_zone_short_description[]" maxlength="200" placeholder="{{translate('Short description')}}" class="form-control min-h-90px ckeditor">{{$available_zone_short_description?->getRawOriginal('value')}}</textarea>
                                </div>
                            </div>
                            @foreach (json_decode($language) as $lang)
                                    <?php
                                        if(isset($available_zone_title->translations)&&count($available_zone_title->translations)){
                                            $available_zone_title_translate = [];
                                            foreach($available_zone_title->translations as $t)
                                            {
                                                if($t->locale == $lang && $t->key=='available_zone_title'){
                                                    $available_zone_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }

                                        }
                                        if(isset($available_zone_short_description->translations)&&count($available_zone_short_description->translations)){
                                            $available_zone_short_description_translate = [];
                                            foreach($available_zone_short_description->translations as $t)
                                            {
                                                if($t->locale == $lang && $t->key=='available_zone_short_description'){
                                                    $available_zone_short_description_translate[$lang]['value'] = $t->value;
                                                }
                                            }

                                        }
                                    ?>
                                <div class="d-none lang_form"
                                     id="{{ $lang }}-form">
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                            ({{ strtoupper($lang) }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span>
                                        </label>
                                        <input type="text" name="available_zone_title[]" maxlength="50" id="{{ $lang }}_title"
                                               class="form-control" value="{{ $available_zone_title_translate[$lang]['value']??'' }}" placeholder="{{ translate('messages.Title') }}">
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="exampleFormControlInput1">{{ translate('Short description') }} ({{ strtoupper($lang) }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 200">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <textarea type="text" name="available_zone_short_description[]" maxlength="200" placeholder="{{translate('Short description')}}" class="form-control min-h-90px ckeditor">{{ $available_zone_short_description_translate[$lang]['value']??'' }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div id="default-form">
                                <div class="form-group">
                                    <label class="input-label"
                                           for="exampleFormControlInput1">{{ translate('messages.Title') }} ({{ translate('Default') }})</label>
                                    <input type="text" name="available_zone_title[]" class="form-control"
                                           placeholder="{{ translate('messages.Title') }}" >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                <div class="form-group mb-0">
                                    <label class="input-label"
                                           for="exampleFormControlInput1">{{ translate('Short description') }}
                                    </label>
                                    <textarea type="text" name="available_zone_short_description[]" placeholder="{{translate('Short description')}}" class="form-control min-h-90px ckeditor"></textarea>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <label class="text-dark d-block mb-2">
                                <strong>{{ translate('Related Image') }}</strong>
                                <small class="text-danger">* ( {{ translate('Ratio') }} 1:1 )</small>
                                <div class="fs-12 opacity-70">
                                    {{ IMAGE_FORMAT.' ' . 'Less Than 2MB' }}
                                </div>
                            </label>

                        </div>
                        <div class="text-center">
                            <label class="position-relative d-inline-block">
                                <img class="img--110 min-height-170px min-width-170px onerror-image image--border"
                                     id="viewer"
                                     data-onerror-image="{{ asset('public/assets/admin/img/upload.png') }}"
                                     src="{{ \App\CentralLogics\Helpers::get_full_url('available_zone_image', $available_zone_image?->value ?? '', $available_zone_image?->storage[0]?->value ?? 'public','upload_image')}}"
                                     alt="logo image">

                                <div class="icon-file-group">
                                    <div class="icon-file">
                                        <i class="tio-edit"></i>
                                    </div>
                                </div>

                                <input type="file"
                                       name="image"
                                       id="customFileEg1"
                                       class="upload-file__input single_file_input"
                                       accept="{{ IMAGE_EXTENSION }}"
                                       hidden>
                            </label>
                        </div>

                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-none border-0 bg-soft-danger">
                    <div class="card-body d-flex">
                        <i class="mr-2 mt-3 text-danger tio-info-outined"></i>
                        <p class="fs-15 text-dark m-0">
                            <strong>{{ translate('Note') }}:</strong> {{ translate('Add a title, description and images for each zone') }}: <a href="{{ route('admin.business-settings.zone.home') }}" target="_blank" class="text--underline text-006AE5">{{ translate('Zone setup') }}</a>. {{ translate('Zones you create appear on the landing page') }}: <a href="{{ route('home') }}" target="_blank" class="text-primary">{{ translate('Admin landing') }}</a>.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="btn--container justify-content-end">
                    <button class="btn btn--reset " type="reset"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                    <button class="btn btn--primary" type="submit"><i class="tio-save"></i> {{translate('Save information')}}</button>
                </div>
            </div>
        </div>
    </form>
    </div>


    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection

@push('script_2')
    <script>
        const prevImage = $('#viewer').attr('src');
        $('#zone-setup-form').on('reset', function(){
            $('#customFileEg1').val(null);
            $('#viewer').attr('src', prevImage);
        })

        function readURL(input, viewer) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();

                reader.onload = function (e) {
                    $('#'+viewer).attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this, 'viewer');
        });
    </script>
@endpush
