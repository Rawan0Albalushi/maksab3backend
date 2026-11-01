@extends('layouts.admin.app')

@section('title',translate('React landing page'))

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
                        {{ translate('React landing page') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('The block inviting businesses to sell through you.') }}</p>
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
            @include('admin-views.business-settings.landing-page-settings.top-menu-links.react-landing-page-links')
        </div>
    </div>

    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
    @if($language)
        <ul class="nav nav-tabs mb-4 border-0">
            <li class="nav-item">
                <a class="nav-link lang_link active"
                href="#"
                id="default-link">{{translate('Default')}}</a>
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
    @php($business_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','react_landing_page')->where('key','business_title')->first())
    @php($business_sub_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','react_landing_page')->where('key','business_sub_title')->first())
    @php($download_app_links = \App\Models\DataSetting::where(['key'=>'download_business_app_links','type'=>'react_landing_page'])->first())
    @php($download_app_links = isset($download_app_links->value)?json_decode($download_app_links->value, true):null)

    @php($business_image=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','react_landing_page')->where('key','business_image')->first())
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <form action="{{ route('admin.business-settings.react-landing-page-settings-update', 'business-section') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-body">

                        <div class="row g-4">
                            <div class="col-md-6">
                                @if ($language)
                                <div class="col-md-12 lang_form default-form">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="business_title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})
                                            <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text" id="business_title" maxlength="30" name="business_title[]" value="{{ $business_title?->getRawOriginal('value')??'' }}" class="form-control" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-12">
                                            <label for="business_sub_title" class="form-label">{{translate('Sub Title')}} ({{ translate('Default') }})
                                            <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 35">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text" id="business_sub_title" maxlength="35" name="business_sub_title[]" value="{{ $business_sub_title?->getRawOriginal('value')??'' }}" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                    @foreach(json_decode($language) as $lang)
                                    <?php
                                    if(isset($business_title->translations)&&count($business_title->translations)){
                                            $business_title_translate = [];
                                            foreach($business_title->translations as $t)
                                            {
                                                if($t->locale == $lang && $t->key=='business_title'){
                                                    $business_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }

                                        }
                                    if(isset($business_sub_title->translations)&&count($business_sub_title->translations)){
                                            $business_sub_title_translate = [];
                                            foreach($business_sub_title->translations as $t)
                                            {
                                                if($t->locale == $lang && $t->key=='business_sub_title'){
                                                    $business_sub_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }

                                        }
                                        ?>
                                    <div class="col-md-12 d-none lang_form" id="{{$lang}}-form1">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="business_title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text" id="business_title{{$lang}}"  maxlength="30" name="business_title[]" value="{{ $business_title_translate[$lang]['value']??'' }}" class="form-control" placeholder="{{translate('Enter title')}}">
                                            </div>
                                            <div class="col-12">
                                                <label for="business_sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 35">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text" id="business_sub_title{{$lang}}"  maxlength="35" name="business_sub_title[]" value="{{ $business_sub_title_translate[$lang]['value']??'' }}" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                            </div>
                                        </div>
                                    </div>
                                        <input type="hidden" name="lang[]" value="{{$lang}}">
                                    @endforeach
                                @else
                                <div class="col-md-12">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="business_title" class="form-label">{{translate('Title')}}</label>
                                            <input id="business_title" type="text" name="business_title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-12">
                                            <label for="business_title" class="form-label">{{translate('Sub Title')}}</label>
                                            <input id="business_sub_title" type="text" name="business_sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                </div>
                                    <input type="hidden" name="lang[]" value="default">
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block mb-2">
                                    {{ translate('Banner') }}  <span class="text--primary">({{ translate('size') }}: 1:1)</span>
                                </label>
                                <label class="upload-img-3 m-0">
                                    <div class="position-relative">
                                    <div class="img">
                                        <img
                                        src="{{\App\CentralLogics\Helpers::get_full_url('business_image', $business_image?->value?? '', $business_image?->storage[0]?->value ?? 'public','aspect_1')}}" data-onerror-image="{{asset('/public/assets/admin/img/aspect-1.png')}}" alt="" class="img__aspect-1 min-w-187px max-w-187px onerror-image">
                                    </div>
                                      <input type="file"  name="image" hidden>
                                         @if (isset($business_image['value']))
                                            <span id="business_image" class="remove_image_button remove-image"
                                                  data-id="business_image"
                                                  data-title="{{translate('warning')}}"
                                                  data-text="<p>{{translate('Are you sure you want to remove this image?')}}</p>"
                                            > <i class="tio-clear"></i></span>
                                            @endif
                                        </div>
                                </label>
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <h5 class="card-title mb-5">
                                    <img src="{{asset('public/assets/admin/img/seller.png')}}" class="mr-2" alt="">
                                    {{translate('Download the Seller App')}}
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-2">
                                            <img src="{{asset('public/assets/admin/img/playstore.png')}}" class="mr-2" alt="">
                                            {{translate('Playstore Button')}}
                                        </h5>
                                        <div class="__bg-F8F9FC-card">
                                            <div class="form-group mb-md-0">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label for="seller_playstore_url" class="form-label text-capitalize m-0">
                                                        {{translate('Download Link')}}

                                                    </label>
                                                    <label class="toggle-switch toggle-switch-sm m-0">
                                                        <input type="checkbox" name="seller_playstore_url_status"

                                                               id="play-store-seller-status"
                                                               data-id="play-store-seller-status"
                                                               data-type="toggle"
                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/play-store-on.png') }}"
                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/play-store-off.png') }}"
                                                               data-title-on="{{ translate('Playstore Button Enabled for Seller') }}"
                                                               data-title-off="{{ translate('Playstore Button Disabled for Seller') }}"
                                                               data-text-on="<p>{{ translate('Playstore button is enabled now everyone can use or see the button') }}</p>"
                                                               data-text-off="<p>{{ translate('Playstore button is disabled now no one can use or see the button') }}</p>"
                                                               class="status toggle-switch-input dynamic-checkbox-toggle"

                                                               value="1" {{(isset($download_app_links) && $download_app_links['seller_playstore_url_status'])?'checked':''}}>
                                                        <span class="toggle-switch-label text mb-0">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </div>
                                                <input id="seller_playstore_url" type="text" placeholder="{{translate('Ex') . ': https://play.google.com/store/apps'}}" class="form-control h--45px" name="seller_playstore_url" value="{{ $download_app_links['seller_playstore_url']??''}}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-2">
                                            <img src="{{asset('public/assets/admin/img/ios.png')}}" class="mr-2" alt="">
                                            {{translate('App Store Button')}}
                                        </h5>
                                        <div class="__bg-F8F9FC-card">
                                            <div class="form-group mb-md-0">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label for="seller_appstore_url" class="form-label text-capitalize m-0">
                                                        {{translate('Download Link')}}

                                                    </label>
                                                    <label class="toggle-switch toggle-switch-sm m-0">
                                                        <input type="checkbox" name="seller_appstore_url_status"

                                                               id="apple-seller-status"
                                                               data-id="apple-seller-status"
                                                               data-type="toggle"
                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/apple-on.png') }}"
                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/apple-off.png') }}"
                                                               data-title-on="{{ translate('App Store Button Enabled for Seller') }}"
                                                               data-title-off="{{ translate('App Store Button Disabled for Seller') }}"
                                                               data-text-on="<p>{{translate('App Store button is enabled now everyone can use or see the button')}}</p>"
                                                               data-text-off="<p>{{translate('App Store button is disabled now no one can use or see the button')}}</p>"
                                                               class="status toggle-switch-input dynamic-checkbox-toggle"

                                                               value="1" {{(isset($download_app_links) && $download_app_links['seller_appstore_url_status'])?'checked':''}}>
                                                        <span class="toggle-switch-label text mb-0">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </div>
                                                <input id="seller_appstore_url" type="text" placeholder="{{translate('Ex') . ': https://www.apple.com/app-store/'}}" class="form-control h--45px" name="seller_appstore_url" value="{{ $download_app_links['seller_appstore_url']??''}}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <h5 class="card-title mb-5">
                                    <img src="{{asset('public/assets/admin/img/dm.png')}}" class="mr-2" alt="">
                                    {{translate('Download the Deliveryman App')}}
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-2">
                                            <img src="{{asset('public/assets/admin/img/playstore.png')}}" class="mr-2" alt="">
                                            {{translate('Playstore Button')}}
                                        </h5>
                                        <div class="__bg-F8F9FC-card">
                                            <div class="form-group mb-md-0">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label for="dm_playstore_url" class="form-label text-capitalize m-0">
                                                        {{translate('Download Link')}}

                                                    </label>
                                                    <label class="toggle-switch toggle-switch-sm m-0">
                                                        <input type="checkbox" name="dm_playstore_url_status"

                                                               id="play-store-dm-status"
                                                               data-id="play-store-dm-status"
                                                               data-type="toggle"
                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/play-store-on.png') }}"
                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/play-store-off.png') }}"
                                                               data-title-on="{{ translate('Playstore Button Enabled for Delivery Man') }}"
                                                               data-title-off="{{ translate('Playstore Button Disabled for Delivery Man') }}"
                                                               data-text-on="{{ translate('Playstore button is enabled now everyone can use or see the button') }}"
                                                               data-text-off="{{ translate('Playstore button is disabled now no one can use or see the button') }}"
                                                               class="status toggle-switch-input dynamic-checkbox-toggle"

                                                               value="1" {{(isset($download_app_links) && $download_app_links['dm_playstore_url_status'])?'checked':''}}>
                                                        <span class="toggle-switch-label text mb-0">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </div>
                                                <input id="dm_playstore_url" type="text" placeholder="{{translate('Ex') . ': https://play.google.com/store/apps'}}" class="form-control h--45px" name="dm_playstore_url" value="{{ $download_app_links['dm_playstore_url']??''}}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-2">
                                            <img src="{{asset('public/assets/admin/img/ios.png')}}" class="mr-2" alt="">
                                            {{translate('App Store Button')}}
                                        </h5>
                                        <div class="__bg-F8F9FC-card">
                                            <div class="form-group mb-md-0">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label for="dm_appstore_url" class="form-label text-capitalize m-0">
                                                        {{translate('Download Link')}}

                                                    </label>
                                                    <label class="toggle-switch toggle-switch-sm m-0">
                                                        <input type="checkbox" name="dm_appstore_url_status"

                                                               id="apple-dm-status"
                                                               data-id="apple-dm-status"
                                                               data-type="toggle"
                                                               data-image-on="{{ asset('/public/assets/admin/img/modal/apple-on.png') }}"
                                                               data-image-off="{{ asset('/public/assets/admin/img/modal/apple-off.png') }}"
                                                               data-title-on="{{ translate('App Store Button Enabled for Delivery Man') }}"
                                                               data-title-off="{{ translate('App Store Button Disabled for Delivery Man') }}"
                                                               data-text-on="<p>{{ translate('App Store button is enabled now everyone can use or see the button') }}</p>"
                                                               data-text-off="<p>{{ translate('App Store button is disabled now no one can use or see the button') }}</p>"
                                                               class="status toggle-switch-input dynamic-checkbox-toggle"

                                                               value="1" {{(isset($download_app_links) && $download_app_links['dm_appstore_url_status'])?'checked':''}}>
                                                        <span class="toggle-switch-label text mb-0">
                                                            <span class="toggle-switch-indicator"></span>
                                                        </span>
                                                    </label>
                                                </div>
                                                <input id="dm_appstore_url" type="text" placeholder="{{translate('Ex') . ': https://www.apple.com/app-store/'}}" class="form-control h--45px" name="dm_appstore_url" value="{{$download_app_links['dm_appstore_url']??''}}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save')}}</button>
                        </div>
                    </div>
                </div>
            </form>
                        <form  id="business_image_form" action="{{ route('admin.remove_image') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{  $business_image?->id}}" >

                <input type="hidden" name="model_name" value="DataSetting" >
                <input type="hidden" name="image_path" value="business_image" >
                <input type="hidden" name="field_name" value="value" >
            </form>

        </div>
    </div>
</div>
@include('admin-views.business-settings.landing-page-settings.partial.how-it-work-react')
@endsection

