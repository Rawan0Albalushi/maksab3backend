@extends('layouts.admin.app')

@section('title',translate('Update promotional section'))

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
                <p class="page-header-desc">{{ translate('Change this promotional block on the admin landing page.') }}</p>
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
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <form action="{{ route('admin.business-settings.promotional-update',[$banner['id']]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card mb-3">
                    <div class="card-body">
                        @if ($language)
                            <div class="row g-3 lang_form" id="default-form">
                                <div class="col-sm-6">
                                    <label for="text" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span>
                                        <span class="form-label-secondary text-danger"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span></label>
                                        <input id="text" required type="text"  maxlength="20" name="title[]" value="{{ $banner?->getRawOriginal('title') }}" class="form-control" placeholder="{{translate('Enter title')}}">
                                </div>
                                <div class="col-sm-6">
                                    <label for="sub_title" class="form-label">{{translate('Sub Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span>
                                        <span class="form-label-secondary text-danger"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span></label>
                                        <input required id="sub_title" type="text"  maxlength="80" name="sub_title[]" value="{{ $banner?->getRawOriginal('sub_title') }}" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                <?php
                                        if(count($banner['translations'])){
                                            $translate = [];
                                            foreach($banner['translations'] as $t)
                                            {
                                                if($t->locale == $lang && $t->key=="title"){
                                                    $translate[$lang]['title'] = $t->value;
                                                }
                                                if($t->locale == $lang && $t->key=="sub_title"){
                                                    $translate[$lang]['sub_title'] = $t->value;
                                                }
                                            }
                                        }
                                    ?>
                                    <div class="row g-3 d-none lang_form" id="{{$lang}}-form">
                                        <div class="col-sm-6">
                                            <label for="title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="title{{$lang}}" type="text"  maxlength="20" name="title[]" value="{{ $translate[$lang]['title']??'' }}" class="form-control" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="sub_title{{$lang}}" type="text"  maxlength="80" name="sub_title[]" value="{{ $translate[$lang]['sub_title']??'' }}" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="title" class="form-label">{{translate('Title')}}</label>
                                        <input id="title" type="text" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="sub_title" class="form-label">{{translate('Sub Title')}}</label>
                                        <input  id="sub_title" type="text" name="sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit" class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Update')}}</button>
                        </div>
                    </div>
                </div>
            </form>
            <form  id="banner_image_form" action="{{ route('admin.remove_image') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{  $banner?->id}}" >
                <input type="hidden" name="model_name" value="AdminPromotionalBanner" >
                <input type="hidden" name="image_path" value="promotional_banner" >
                <input type="hidden" name="field_name" value="image" >
            </form>
        </div>
    </div>
</div>
    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection

